<?php
/*
 * Created on   : Tue Aug 18 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AppointmentSlotService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Appointments;

use App\Models\{BookableService, User};
use App\Services\Dispatch\GapFillSuggester;
use Carbon\CarbonImmutable;

/**
 * Slot-Ermittlung für die Portal-Terminbuchung (Feature 087, MVP-666).
 *
 * Die Fenster kommen aus der Dispositions-Leerzeit (GapFillSuggester), nach
 * außen aber nur als **anonyme Zeitfenster**: keine Mitarbeiternamen, keine
 * Auslastung, keine Anzahl freier Kräfte — ein Slot ist buchbar oder nicht.
 * Deshalb werden die Fenster über alle Mitarbeiter DEDUPLIZIERT, bevor sie
 * das Haus verlassen.
 */
class AppointmentSlotService {
    public function __construct(private readonly GapFillSuggester $gaps) {}

    /**
     * Anonyme Zeitfenster eines Tages für eine Leistungsart.
     *
     * @return list<array{start: CarbonImmutable, end: CarbonImmutable}>
     */
    public function slotsFor(BookableService $service, CarbonImmutable $date, int $limit = 12): array {
        $required = $service->duration_minutes + $service->buffer_minutes;
        $earliest = CarbonImmutable::parse($service->earliestStart());

        $users = User::query()
            ->where('organization_id', $service->organization_id)
            ->get();

        // Qualifikations-Filter (Folgepunkt aus MVP-666): Verlangt die
        // Leistungsart eine Qualifikation, zählen nur Fenster von Kräften,
        // die sie AM TAG DES TERMINS gültig halten - ein Slot, den am Ende
        // niemand fahren darf, wäre ein leeres Versprechen an den Kunden.
        if ($service->required_qualification_id !== null) {
            $requiredId = (int) $service->required_qualification_id;
            $users = $users->filter(function (User $user) use ($requiredId, $date): bool {
                foreach ($user->qualifications as $qualification) {
                    if ((int) $qualification->id !== $requiredId) {
                        continue;
                    }
                    $pivot = $qualification->getRelationValue('pivot');
                    $until = $pivot?->getAttribute('valid_until');

                    return $until === null || ! $date->greaterThan(\Illuminate\Support\Carbon::parse((string) $until));
                }

                return false;
            });
        }

        $windows = [];
        foreach ($users as $user) {
            // freeSlots liefert Uhrzeiten als H:i-Strings des Tages.
            foreach ($this->gaps->freeSlots($user, $date) as $slot) {
                $start = $date->setTimeFromTimeString((string) $slot['start']);
                $end = $date->setTimeFromTimeString((string) $slot['end']);
                if ($end->lessThanOrEqualTo($start)) {
                    continue;
                }

                // Im 30-Minuten-Raster diskretisieren, damit die Fenster
                // vergleichbar (und damit deduplizierbar) werden.
                $cursor = $start->minute % 30 === 0 ? $start : $start->addMinutes(30 - ($start->minute % 30))->setSeconds(0);
                while ($cursor->addMinutes($required)->lessThanOrEqualTo($end)) {
                    if ($cursor->greaterThanOrEqualTo($earliest)) {
                        $windows[$cursor->format('Y-m-d H:i')] = $cursor;
                    }
                    $cursor = $cursor->addMinutes(30);
                }
            }
        }

        ksort($windows);

        $out = [];
        foreach (array_slice(array_values($windows), 0, $limit) as $start) {
            $out[] = [
                'start' => $start,
                'end' => $start->addMinutes($service->duration_minutes),
            ];
        }

        return $out;
    }
}
