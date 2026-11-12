<?php
/*
 * Created on   : Wed Nov 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TimeEntrySyncHandler.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Timekeeping\Sync;

use App\Http\Requests\Time\QuickTimeEntryRequest;
use App\Models\Platform\User;
use App\Models\Time\TimeEntry;
use App\Services\Sync\Contracts\SyncCommandHandler;
use App\Services\Sync\{SyncConflictException, SyncFormRequest};
use App\Services\TimeApproval\DayCloseService;
use App\Services\Timekeeping\ManualTimeBooking;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Gate;
use RuntimeException;

/**
 * Offline erfasste Projektzeit aus der Eingabeleiste (MVP-1593), Typ `time.entry`.
 *
 * Läuft über Regeln und Dienst des Online-Formulars. Zwei Fälle sind Konflikte
 * statt Ablehnungen, weil erst der Nutzer entscheiden kann: Der Tag wurde
 * zwischen Erfassung und Abgleich abgeschlossen (geschrieben wird am Abschluss
 * vorbei nie), oder eine Pflichtangabe fehlt.
 */
final class TimeEntrySyncHandler implements SyncCommandHandler {
    public function __construct(
        private readonly ManualTimeBooking $booking,
        private readonly DayCloseService $dayClose,
    ) {}

    /** @return list<string> */
    public function types(): array {
        return ['time.entry'];
    }

    public function handle(User $user, string $type, array $payload): string {
        if ($type !== 'time.entry') {
            throw new RuntimeException('Unbekannter Sync-Befehlstyp: ' . $type);
        }
        if (! Gate::forUser($user)->allows('create', TimeEntry::class)) {
            throw new RuntimeException((string) __('sync.error.forbidden'));
        }

        $missing = $this->missingFields($payload);
        if ($missing !== []) {
            throw new SyncConflictException(
                (string) __('sync.conflict.missing_fields', ['fields' => implode(', ', $missing)]),
                ['missing' => implode(', ', $missing)],
                '',
            );
        }

        $day = $this->day((string) $payload['date']);
        if ($day !== null && ($lock = $this->dayClose->bookingLock($user, $day)) !== null) {
            throw new SyncConflictException(
                (string) __('sync.conflict.day_locked', ['date' => $day->format('d.m.Y')]),
                ['date' => $day->toDateString(), 'lock' => (string) __('sync.conflict.lock.' . $lock)],
                '',
            );
        }

        $data = SyncFormRequest::validate(QuickTimeEntryRequest::class, $payload, $user)->validated();

        return 'time_entries:' . $this->booking->book($user, $data)->id;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return list<string> Anzeigenamen der fehlenden Pflichtangaben
     */
    private function missingFields(array $payload): array {
        $missing = [];
        if (blank($payload['project_id'] ?? null)) {
            $missing[] = (string) __('validation.attributes.project_id');
        }
        if (blank($payload['date'] ?? null)) {
            $missing[] = (string) __('validation.attributes.date');
        }
        if (blank($payload['minutes'] ?? null) && (blank($payload['start_time'] ?? null) || blank($payload['end_time'] ?? null))) {
            $missing[] = (string) __('validation.attributes.minutes');
        }

        return $missing;
    }

    /** Kalendertag der Buchung; ein unlesbares Datum meldet die Validierung. */
    private function day(string $raw): ?CarbonImmutable {
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', trim($raw)) !== 1) {
            return null;
        }

        try {
            return CarbonImmutable::parse(trim($raw))->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }
}
