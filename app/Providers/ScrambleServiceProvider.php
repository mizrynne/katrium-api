<?php

namespace App\Providers;

use App\Enums\RoleName;
use App\Models\User;
use Dedoc\Scramble\Scramble;
use Dedoc\Scramble\Support\Generator\OpenApi;
use Dedoc\Scramble\Support\Generator\Operation;
use Dedoc\Scramble\Support\Generator\Response;
use Dedoc\Scramble\Support\Generator\Schema;
use Dedoc\Scramble\Support\Generator\SecurityScheme;
use Dedoc\Scramble\Support\Generator\Types\ObjectType;
use Dedoc\Scramble\Support\Generator\Types\StringType;
use Dedoc\Scramble\Support\RouteInfo;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Spatie\Permission\Middleware\PermissionMiddleware;

class ScrambleServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // Outside APP_ENV=local, only signed-in staff (web session) may open /docs/api.
        Gate::define('viewApiDocs', fn (?User $user = null): bool => $user?->hasAnyRole([
            RoleName::SUPERADMIN->value,
            RoleName::MANAGER->value,
        ]) ?? false);

        Scramble::configure()
            ->routes(fn (Route $route): bool => Str::startsWith($route->uri(), 'api/v1/')
                && $route->getName() !== 'webhooks.beds24'      // machine-to-machine, shared-secret header
                && ! Str::contains($route->uri(), 'broadcasting')) // Reverb/Echo auth endpoint
            ->withDocumentTransformers(
                fn (OpenApi $openApi) => $openApi->secure(SecurityScheme::http('bearer'))
            )
            ->withOperationTransformers(function (Operation $operation, RouteInfo $routeInfo): void {
                $this->annotatePermissions($operation, $routeInfo);
                $this->ensureAuthErrorResponses($operation, $routeInfo);
            });
    }

    /**
     * Single source of truth: permission slugs are read from the route's
     * PermissionMiddleware::using(...) so docs can never drift from routes/api.php.
     */
    private function annotatePermissions(Operation $operation, RouteInfo $routeInfo): void
    {
        $prefix = PermissionMiddleware::class.':';

        $slugs = collect($routeInfo->route->gatherMiddleware())
            ->filter(fn ($m) => is_string($m) && str_starts_with($m, $prefix))
            ->map(fn (string $m) => explode(',', substr($m, strlen($prefix)))[0]) // drop optional guard arg
            ->flatMap(fn (string $m) => explode('|', $m))
            ->map(fn (string $s) => trim($s))
            ->filter()
            ->unique()
            ->values();

        if ($slugs->isEmpty()) {
            return;
        }

        $label = $slugs->count() > 1 ? 'Requires any one of these permissions' : 'Requires permission';
        $list = $slugs->map(fn (string $s) => "`{$s}`")->implode(', ');

        $operation->description(trim(
            ($operation->description ?? '')."\n\n*{$label}: {$list}*  \n*Superadmin bypasses all permission checks.*"
        ));
        $operation->setExtensionProperty('required-permissions', $slugs->all());
    }

    private function ensureAuthErrorResponses(Operation $operation, RouteInfo $routeInfo): void
    {
        $middleware = collect($routeInfo->route->gatherMiddleware())->filter(fn ($m) => is_string($m));

        $needs401 = $middleware->contains(fn (string $m) => str_starts_with($m, 'auth:'));
        $needs403 = $middleware->contains(fn (string $m) => str_starts_with($m, PermissionMiddleware::class.':'));

        foreach ([401 => $needs401, 403 => $needs403] as $code => $needed) {
            if (! $needed || $this->hasResponse($operation, $code)) {
                continue;
            }

            $body = (new ObjectType)
                ->addProperty('message', new StringType)
                ->setRequired(['message']);

            $operation->addResponse(
                Response::make($code)
                    ->description($code === 401 ? 'Unauthenticated.' : 'Forbidden: missing permission or role.')
                    ->setContent('application/json', Schema::fromType($body))
            );
        }
    }

    private function hasResponse(Operation $operation, int $code): bool
    {
        return collect($operation->responses)->contains(fn ($r) => $r instanceof Response && $r->code === $code);
    }
}