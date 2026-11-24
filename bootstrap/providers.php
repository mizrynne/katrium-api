<?php

// Telescope is NOT listed here: it is a dev dependency and is registered conditionally
// (local only, and only if installed) in AppServiceProvider::register().
return [
    App\Providers\AppServiceProvider::class,
    App\Providers\HorizonServiceProvider::class,
    App\Providers\ScrambleServiceProvider::class,
];
