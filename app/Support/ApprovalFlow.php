<?php

namespace App\Support;

use App\Models\ActivityLog;
use App\Models\Person;
use App\Models\PersonReview;
use App\Models\User;
use App\Notifications\ReviewUpdate;

/**
 * Kubadilisha hatua ya idhini ya rekodi pamoja na historia yake
 * (person_reviews + Activity Log) na kengele kwa aliyesajili.
 */
class ApprovalFlow
{
    /** Badilisha hatua, andika historia ya idhini na Activity Log. */
    public static function move(Person $person, string $stage, string $action, ?string $note, array $extra = [], ?array $checks = null): void
    {
        $person->forceFill(['stage' => $stage] + $extra)->save();

        PersonReview::create([
            'person_id' => $person->id,
            'user_id' => auth()->id(),
            'action' => $action,
            'note' => $note,
            'checklist' => $checks,
        ]);

        $text = [
            'draft' => 'Saved as draft',
            'submitted' => 'Submitted for approval',
            'edited' => 'Sensitive details changed, sent for re-approval',
            'resubmitted' => 'Resubmitted after correction',
            'claimed' => 'Started reviewing',
            'released' => 'Released back to the queue',
            'approved' => 'Approved',
            'returned' => 'Returned for correction',
            'rejected' => 'Rejected',
        ][$action] ?? ucfirst($action);

        ActivityLog::record("approval.{$action}", "{$text}: {$person->full_name}" . ($stage === Person::APPROVED ? " (NIN {$person->nin})" : '')
            . ($note && $action !== 'approved' ? " — {$note}" : ''), $person);
    }

    /** Kengele kwa aliyewasilisha (au aliyesajili), si kwa aliyefanya uamuzi. */
    public static function notify(Person $person, string $action, ?string $note, User $by): void
    {
        $to = User::find($person->submitted_by ?: $person->created_by);

        if ($to && $to->id !== $by->id) {
            $to->notify(new ReviewUpdate($person, $action, $note, $by->name));
        }
    }
}
