<?php
/*
 * Created on   : Tue Sep 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ClubEventReminderScan.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Notification\DeadlineScans;

use App\Enums\Club\ClubParticipationStatus;
use App\Models\Calendar\Event;
use App\Models\Club\{ClubEventParticipation, ClubMember};
use App\Services\Club\ClubMemberNotifier;
use App\Services\Notification\NotificationDispatcher;
use Carbon\CarbonImmutable;

/**
 * Erinnerung an angemeldete Vereinstermine (Feature 159, MVP-845): alle
 * Termine, die innerhalb des Vorlaufs beginnen; je Mitglied und Beginn genau
 * eine Nachricht — der Notifier dedupliziert, ein erneuter Lauf verdoppelt nichts.
 */
class ClubEventReminderScan extends AbstractDeadlineScan {
    /** Vorlauf in Stunden; der Scan läuft täglich, das Fenster deckt einen Lauf ab. */
    public const LEAD_HOURS = 24;

    public function __construct(
        private readonly ClubMemberNotifier $notifier,
    ) {}

    public function key(): string {
        return 'club-reminder';
    }

    public function run(NotificationDispatcher $dispatcher, DeadlineScanOptions $options): int {
        unset($dispatcher);
        $now = CarbonImmutable::now();
        $sent = 0;

        $events = Event::query()
            ->withoutGlobalScopes()
            ->whereHas('clubDetails')
            ->whereNull('cancelled_at')
            ->where('started_at', '>=', $now)
            ->where('started_at', '<', $now->addHours(self::LEAD_HOURS))
            ->with('organization')
            ->get();

        foreach ($events as $event) {
            $memberIds = ClubEventParticipation::query()
                ->withoutGlobalScopes()
                ->where('event_id', $event->id)
                ->where('status', ClubParticipationStatus::Registered->value)
                ->pluck('club_member_id');
            foreach (ClubMember::query()->withoutGlobalScopes()->whereIn('id', $memberIds)->get() as $member) {
                $sent += $this->notifier->reminder($event, $member);
            }
        }

        if ($sent > 0) {
            $options->info("club-reminder: {$sent} Erinnerungen versendet.");
        }

        return $sent;
    }
}
