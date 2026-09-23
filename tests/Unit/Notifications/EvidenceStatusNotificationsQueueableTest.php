<?php

namespace Tests\Unit\Notifications;

use App\Notifications\Evidence\EvidenceApprovalConfirmedNotification;
use App\Notifications\Evidence\EvidenceApprovedNotification;
use App\Notifications\Evidence\EvidenceExemptedNotification;
use App\Notifications\Evidence\EvidenceExemptionConfirmedNotification;
use App\Notifications\Evidence\EvidenceNeedsAdjustmentNotification;
use App\Notifications\Evidence\EvidenceOverdueNotification;
use App\Notifications\Evidence\EvidencePendingReviewNotification;
use App\Notifications\Evidence\EvidenceReturnConfirmedNotification;
use App\Notifications\Evidence\EvidenceSubmissionConfirmedNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * El envío no debe bloquear la acción del usuario (aprobar, devolver,
 * etc.) — las 9 clases deben ir en cola, ver docs/manual-tecnico.md.
 */
class EvidenceStatusNotificationsQueueableTest extends TestCase
{
    public static function notificationClasses(): array
    {
        return [
            [EvidenceSubmissionConfirmedNotification::class],
            [EvidencePendingReviewNotification::class],
            [EvidenceReturnConfirmedNotification::class],
            [EvidenceNeedsAdjustmentNotification::class],
            [EvidenceApprovalConfirmedNotification::class],
            [EvidenceApprovedNotification::class],
            [EvidenceOverdueNotification::class],
            [EvidenceExemptionConfirmedNotification::class],
            [EvidenceExemptedNotification::class],
        ];
    }

    #[DataProvider('notificationClasses')]
    public function test_notification_implements_should_queue(string $class): void
    {
        $this->assertTrue(is_subclass_of($class, ShouldQueue::class));
    }
}
