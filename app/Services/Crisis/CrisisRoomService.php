<?php
/*
 * Created on   : Sun Sep 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CrisisRoomService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Crisis;

use App\Models\Asset\Asset;
use App\Models\Crisis\{CrisisCase, CrisisMapPoint, CrisisRoomPresence};
use App\Models\Customer\Customer;
use App\Models\Platform\User;
use Illuminate\Support\Carbon;

/**
 * Krisenraum (MVP-963): Anwesenheit über einen Herzschlag der geöffneten
 * Fallseite und die Lagekarte aus verknüpften Objekten und eigenen Punkten.
 */
class CrisisRoomService {
    /** Wer innerhalb dieser Spanne keinen Herzschlag schickt, gilt als abwesend. */
    public const PRESENCE_SECONDS = 90;

    /** @return list<array{name: string, seen: string}> */
    public function heartbeat(CrisisCase $case, User $user): array {
        CrisisRoomPresence::query()->updateOrCreate(
            ['crisis_case_id' => $case->id, 'user_id' => $user->id],
            ['organization_id' => $case->organization_id, 'last_seen_at' => Carbon::now()],
        );

        return $this->present($case);
    }

    /** @return list<array{name: string, seen: string}> */
    public function present(CrisisCase $case): array {
        return array_values(CrisisRoomPresence::query()
            ->where('crisis_case_id', $case->id)
            ->where('last_seen_at', '>=', Carbon::now()->subSeconds(self::PRESENCE_SECONDS))
            ->with('user:id,name')
            ->orderBy('last_seen_at')
            ->get()
            ->map(static fn (CrisisRoomPresence $p): array => ['name' => (string) ($p->user->name ?? '—'), 'seen' => $p->last_seen_at->toIso8601String()])
            ->all());
    }

    /** @return list<array{lat: float, lng: float, label: string, layer: string, color: string}> */
    public function markers(CrisisCase $case): array {
        $markers = [];
        foreach ($case->links as $link) {
            $target = $link->linkable;
            [$lat, $lng, $label] = match (true) {
                $target instanceof Asset => [$target->location_lat, $target->location_lng, $target->name],
                $target instanceof Customer => [$target->address_lat, $target->address_lng, $target->name],
                default => [null, null, ''],
            };
            if ($lat !== null && $lng !== null) {
                $markers[] = ['lat' => (float) $lat, 'lng' => (float) $lng, 'label' => (string) $label, 'layer' => (string) __('crisis.room.layer.linked'), 'color' => '#2563eb'];
            }
        }
        foreach (CrisisMapPoint::query()->where('crisis_case_id', $case->id)->orderBy('id')->get() as $point) {
            $markers[] = ['lat' => (float) $point->lat, 'lng' => (float) $point->lng, 'label' => $point->label, 'layer' => (string) __('crisis.room.kind.' . $point->kind), 'color' => $point->kind === 'incident' ? '#dc2626' : ($point->kind === 'closure' ? '#f59e0b' : '#16a34a')];
        }

        return $markers;
    }
}
