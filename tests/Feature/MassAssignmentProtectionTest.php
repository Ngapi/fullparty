<?php

use App\Models\NotificationEvent;
use App\Models\User;
use App\Models\UserActivityApplicationDefault;
use App\Models\UserHomeProfile;
use App\Models\UserNotification;

it('keeps privilege and system-owned fields out of mass assignment allowlists', function () {
    $user = new User;
    $event = new NotificationEvent;
    $notification = new UserNotification;
    $applicationDefault = new UserActivityApplicationDefault;
    $homeProfile = new UserHomeProfile;

    expect($user->isFillable('password'))->toBeFalse()
        ->and($user->isFillable('email_verified_at'))->toBeFalse()
        ->and($user->isFillable('is_admin'))->toBeFalse()
        ->and($event->isFillable('is_mandatory'))->toBeFalse()
        ->and($event->isFillable('actor_user_id'))->toBeFalse()
        ->and($event->isFillable('subject_type'))->toBeFalse()
        ->and($event->isFillable('subject_id'))->toBeFalse()
        ->and($notification->isFillable('aggregate_count'))->toBeFalse()
        ->and($notification->isFillable('read_at'))->toBeFalse()
        ->and($applicationDefault->isFillable('user_id'))->toBeFalse()
        ->and($homeProfile->isFillable('user_id'))->toBeFalse();
});
