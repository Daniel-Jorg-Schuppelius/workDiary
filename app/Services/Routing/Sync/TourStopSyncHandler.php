<?php
/*
 * Created on   : Thu Oct 15 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TourStopSyncHandler.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Routing\Sync;

use App\Models\Diary\{Tour, TourStop};
use App\Models\Platform\User;
use App\Services\Routing\TourService;
use App\Services\Sync\Contracts\SyncCommandHandler;
use App\Support\Sqid;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\{Gate, Validator};
use RuntimeException;

/**
 * Offline-Stopp-Ist der Fahreransicht (MVP-1183): „angekommen“ und
 * „abgefahren“ mit dem Zeitpunkt der Erfassung auf dem Gerät.
 */
final class TourStopSyncHandler implements SyncCommandHandler {
    public function __construct(private readonly TourService $tours) {}

    /** @return list<string> */
    public function types(): array {
        return ['tour.stop-arrive', 'tour.stop-depart'];
    }

    public function handle(User $user, string $type, array $payload): string {
        $data = Validator::make($payload, [
            'stop' => ['required', 'string', 'max:64'],
            'at' => ['nullable', 'date'],
            'outcome' => [$type === 'tour.stop-depart' ? 'required' : 'nullable', 'in:' . TourStop::OUTCOME_DONE . ',' . TourStop::OUTCOME_NOT_MET],
        ])->validate();

        $stopId = Sqid::decodeOrNumeric(TourStop::class, (string) $data['stop']);
        $stop = $stopId !== null ? TourStop::query()->find($stopId) : null;
        $tour = $stop?->tour;
        if (! $stop instanceof TourStop || ! $tour instanceof Tour) {
            throw new RuntimeException((string) __('Stopp nicht gefunden.'));
        }
        if (! Gate::forUser($user)->allows('update', $tour)) {
            throw new RuntimeException((string) __('Keine Berechtigung für diese Tour.'));
        }
        $at = isset($data['at']) ? CarbonImmutable::parse((string) $data['at']) : null;

        if ($type === 'tour.stop-arrive') {
            $this->tours->arriveAtStop($stop, $user, $at);
        } else {
            $this->tours->departStop($stop, $user, (string) $data['outcome'], $at);
        }

        return 'tour_stops:' . $stop->id;
    }
}
