<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('Src.Domain.User.Models.User.{id}', function ($user, $id) {
    return (string) $user->id === (string) $id;
});

Broadcast::channel('Src.Domain.AppUser.Models.AppUser.{id}', function ($user, $id) {
    return (string) $user->id === (string) $id;
});

Broadcast::channel('user.{id}', function ($user, $id) {
    return (string) $user->id === (string) $id;
});

Broadcast::channel('organization.{id}', function ($user, $id) {
    if ($user->hasRole('admin')) {
        return true;
    }

    return $user->organizations()->where('organizations.id', $id)->exists();
});
