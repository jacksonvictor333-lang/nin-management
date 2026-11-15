<?php

namespace App\Notifications;

use App\Models\Person;
use Illuminate\Notifications\Notification;

/**
 * Taarifa (kengele) kwa aliyesajili rekodi: imeidhinishwa, imerudishwa au imekataliwa.
 */
class ReviewUpdate extends Notification
{
    public function __construct(
        private Person $person,
        private string $action,
        private ?string $note = null,
        private ?string $by = null,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $name = $this->person->full_name;

        $message = match ($this->action) {
            'approved' => "{$name} was approved. NIN {$this->person->nin} issued.",
            'returned' => "{$name} was returned for correction.",
            'rejected' => "{$name} was rejected.",
            default => "{$name}: {$this->action}.",
        };

        return [
            'person_id' => $this->person->id,
            'action' => $this->action,
            'message' => $message,
            'note' => $this->note,
            'by' => $this->by,
        ];
    }
}
