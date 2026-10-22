<?php

use Illuminate\Support\Facades\Broadcast;
use App\Support\PartnerScope;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('calendar', fn ($user) =>
    ! PartnerScope::applies($user) && $user->canAny(['view:calendar', 'manage:calendar'])
);
