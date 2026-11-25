<?php

use App\Support\PartnerScope;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('calendar', fn ($user) =>
    ! PartnerScope::applies($user) && $user->canAny(['view:calendar', 'manage:calendar'])
);


Broadcast::channel('calendar.partner.{userId}', fn ($user, $userId) =>
    (int) $user->id === (int) $userId
    && PartnerScope::applies($user)
    && $user->canAny(['view:calendar', 'manage:calendar'])
);