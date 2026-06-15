<?php
/*
 * Created on   : Wed Jun 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : NotificationLinks.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Support;

use App\Models\OpenIssue;
use Illuminate\Database\Eloquent\Model;
use Throwable;

/**
 * Best-effort-Links für Benachrichtigungs-Payloads (MVP-018).
 * Offene Punkte haben keine eigene Detailseite — sie leben im Panel ihrer
 * Subjekt-Seite (Tagebucheintrag, Projekt, Kunde, Asset).
 */
final class NotificationLinks {
    public static function openIssueUrl(OpenIssue $issue): ?string {
        $issue->loadMissing('subject');

        return self::subjectUrl($issue->subject);
    }

    public static function subjectUrl(?Model $subject): ?string {
        if ($subject === null) {
            return null;
        }

        try {
            return match ($subject::class) {
                \App\Models\DiaryEntry::class => route('diary.show', $subject),
                \App\Models\Project::class => route('projects.show', $subject),
                \App\Models\Customer::class => route('customers.show', $subject),
                \App\Models\Asset::class => route('assets.show', $subject),
                \App\Models\SafetyEvent::class => route('safety-events.show', $subject),
                default => null,
            };
        } catch (Throwable) {
            return null;
        }
    }
}
