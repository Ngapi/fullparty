<?php

namespace App\Services\Integrations;

use App\Models\IntegrationClient;
use App\Models\User;
use App\Services\Notifications\NotificationService;
use App\Support\Notifications\NotificationCategory;
use Illuminate\Support\Str;

class IntegrationAdminNotificationService
{
    public function __construct(
        private readonly NotificationService $notificationService,
    ) {}

    public function notifyEventDeliveryFailed(IntegrationClient $client, string $event, string $error): void
    {
        $admins = User::query()
            ->where('is_admin', true)
            ->get();

        if ($admins->isEmpty()) {
            return;
        }

        $notificationEvent = $this->notificationService->createEvent(
            type: 'integration.event_delivery_failed',
            category: NotificationCategory::SYSTEM_NOTICES,
            titleKey: 'notifications.integrations.delivery_failed.title',
            bodyKey: 'notifications.integrations.delivery_failed.body',
            messageParams: [
                'client' => $client->name,
                'event' => $event,
                'error' => Str::limit($error, 240, '...'),
            ],
            actionUrl: route('admin.integrations.index'),
            subject: $client,
            payload: [
                'integration_client_id' => $client->id,
                'integration_client_type' => $client->type,
                'event' => $event,
            ],
            isMandatory: true,
        );

        $this->notificationService->sendInAppNotifications($notificationEvent, $admins);
    }
}
