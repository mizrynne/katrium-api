<?php

use Dedoc\Scramble\Http\Middleware\RestrictedDocsAccess;

return [
    /*
     * Only routes under this prefix are documented. Paths in the spec are relative to it,
     * so the server URL becomes {APP_URL}/api/v1.
     */
    'api_path' => 'api/v1',

    'api_domain' => null,

    'export_path' => 'api.json',

    'info' => [
        'version' => env('API_VERSION', '1.0.0'),
        'description' => <<<'MD'
KatriuM PMS headless REST API (KM Staycations).

**Auth:** Sanctum Bearer token issued by `GET /auth/google/callback` (Google OAuth only, pre-provisioned accounts).
Send `Authorization: Bearer <token>`.

**Access control:** Spatie roles + direct permission overrides. Each endpoint lists its required permission slug(s).
`Superadmin` bypasses every permission check.

**Errors:** `401` unauthenticated, `403` forbidden, `404` not found, `422` validation (`message` + `errors` record).
Booking rule violations return `422` with `success`, `code`, `message`, `errors`, `conflict`.
MD,
    ],

    'ui' => [
        'title' => 'KatriuM API',
        'theme' => 'light',
        'hide_try_it' => false,
        'hide_schemas' => false,
        'logo' => '',
        'try_it_credentials_policy' => 'omit', // Bearer tokens, not cookies
        'layout' => 'responsive',
    ],

    'servers' => null,

    'enum_cases_description_strategy' => 'description',
    'enum_cases_names_strategy' => false,
    'flatten_deep_query_parameters' => true,

    /*
     * RestrictedDocsAccess: always allowed when APP_ENV=local, otherwise the `viewApiDocs`
     * gate (defined in ScrambleServiceProvider) decides.
     */
    'middleware' => [
        'web',
        RestrictedDocsAccess::class,
    ],

    'extensions' => [],
];