<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (string) $user->id === (string) $id;
});

Broadcast::channel('user.{id}', function ($user, $id) {
    return (string) $user->id === (string) $id;
});

Broadcast::channel('organization.{id}', function ($user, $id) {
    if ($user->hasRole('admin')) {
        return true;
    }

    return (string) $user->organization_id === (string) $id;
});
