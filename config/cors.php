<?php

/** Comma-separated env list -> trimmed array without empties. */
$list = fn (string $key, string $default = ''): array => array_values(array_filter(
    array_map('trim', explode(',', (string) env($key, $default)))
));

return [

    'paths' => ['api/*'],

    'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],

    // e.g. CORS_ALLOWED_ORIGINS=http://localhost:5173,https://katrium.example.com
    'allowed_origins' => $list('CORS_ALLOWED_ORIGINS', 'http://localhost:5173,http://127.0.0.1:5173'),

    // Regexes WITH delimiters, e.g. CORS_ALLOWED_ORIGIN_PATTERNS=#^https://.*\.run\.app$#
    'allowed_origins_patterns' => $list('CORS_ALLOWED_ORIGIN_PATTERNS'),

    'allowed_headers' => ['Authorization', 'Content-Type', 'Accept', 'X-Requested-With', 'X-Socket-Id'],

    'exposed_headers' => ['Retry-After', 'X-RateLimit-Limit', 'X-RateLimit-Remaining'],

    // Cache preflight answers for 10 minutes (was 0: a preflight before every request).
    'max_age' => 600,

    // Bearer tokens, no cookies: credentialed CORS is not needed.
    'supports_credentials' => false,

];
