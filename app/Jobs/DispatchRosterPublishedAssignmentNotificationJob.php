<?php

namespace App\Jobs;

use App\Services\Notifications\AssignmentNotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class DispatchRosterPublishedAssignmentNotificationJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly int $slotId,
        public readonly ?int $applicationId,
        public readonly ?int $characterId,
        public readonly ?int $actorId,
    ) {}

    public function handle(AssignmentNotificationService $assignmentNotificationService): void
    {
        $assignmentNotificationService->sendRosterPublishedNotification(
            slotId: $this->slotId,
            applicationId: $this->applicationId,
            characterId: $this->characterId,
            actorId: $this->actorId,
        );
    }
}
