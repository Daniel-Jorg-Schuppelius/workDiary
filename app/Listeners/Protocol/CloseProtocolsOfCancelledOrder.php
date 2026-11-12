<?php
/*
 * Created on   : Wed Nov 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CloseProtocolsOfCancelledOrder.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Listeners\Protocol;

use App\Enums\Protocol\ProtocolStatus;
use App\Events\Diary\OrderCancelled;
use App\Models\Diary\DiaryEntry;
use App\Models\Platform\User;
use App\Models\Protocol\Protocol;
use App\Services\Protocol\ProtocolService;
use App\Support\MorphMap;

/**
 * Storno eines Auftrags (MVP-1596): offene Protokolle wandern ins Archiv.
 * Unterschriebene bleiben, sie sind Nachweise.
 */
final class CloseProtocolsOfCancelledOrder {
    public function __construct(private readonly ProtocolService $protocols) {}

    public function handle(OrderCancelled $event): void {
        $actor = $event->actorUserId !== null ? User::query()->find($event->actorUserId) : null;
        if (! $actor instanceof User) {
            return;
        }

        $open = Protocol::query()
            ->where('organization_id', $event->organizationId)
            ->where('subject_type', MorphMap::alias(DiaryEntry::class))
            ->where('subject_id', $event->diaryEntryId)
            ->whereIn('status', [ProtocolStatus::Draft->value, ProtocolStatus::InReview->value])
            ->get();

        foreach ($open as $protocol) {
            if ($protocol->status === ProtocolStatus::InReview) {
                $protocol = $this->protocols->returnToDraft($protocol, $actor, $event->reason);
            }
            $this->protocols->archive($protocol, $actor);
        }
    }
}
