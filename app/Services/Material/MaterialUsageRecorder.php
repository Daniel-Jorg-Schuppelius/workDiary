<?php
/*
 * Created on   : Wed Nov 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MaterialUsageRecorder.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Material;

use App\Models\Material\{Material, MaterialUsage};
use App\Models\Platform\User;
use App\Models\Project\Project;
use App\Models\Time\Timesheet;
use App\Services\Timesheet\TimesheetResolver;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\{DB, Gate};
use Illuminate\Validation\ValidationException;

/**
 * Materialverbrauch am Stundenzettel (MVP-1594): Dialog am Zettel, API,
 * „Heute“ und Offline-Befehl `material.usage` schreiben hierüber. Leere Felder
 * kommen aus dem Materialstamm. Ein Lagerabgang entsteht dabei nicht — der
 * Verbrauch am Zettel ist Abrechnungs- und Nachweisposten, kein Lagerbeleg.
 */
final class MaterialUsageRecorder {
    public function __construct(private readonly TimesheetResolver $timesheets) {}

    /** @param  array<string, mixed>  $data  geprüfte Felder des SaveMaterialUsageRequest */
    public function record(Timesheet $timesheet, array $data): MaterialUsage {
        if (! empty($data['material_id'])) {
            $material = Material::query()->find((int) $data['material_id']);
            if ($material instanceof Material) {
                $data['unit_price'] = $data['unit_price'] ?? $material->default_unit_price;
                $data['tax_rate'] = $data['tax_rate'] ?? $material->tax_rate;
                $data['unit'] = ($data['unit'] ?? '') ?: $material->unit;
                $data['description'] = ($data['description'] ?? '') ?: $material->name;
            }
        }

        // Die Tagesbuchung kennt den Zettel erst hier; der Request prüft nur mit Zettel in der Route (MVP-1627).
        if (MaterialUsage::exceedsTimesheetSum($timesheet, $data['quantity'] ?? 0, $data['unit_price'] ?? 0)) {
            throw ValidationException::withMessages(['quantity' => (string) __('Menge × Einzelpreis übersteigt die höchste speicherbare Summe des Stundenzettels.')]);
        }

        return $timesheet->materialUsages()->create($data);
    }

    /**
     * Verbrauch eines Tages ohne gewählten Zettel: landet auf dem offenen
     * Stundenzettel von Projekt, Person und Tag (wie die Stoppuhr), der bei
     * Bedarf angelegt wird. Ohne Recht am Zettel wird nichts angelegt.
     *
     * @param  array<string, mixed>  $data
     */
    public function recordForDay(User $user, Project $project, CarbonImmutable $day, array $data): MaterialUsage {
        return DB::transaction(function () use ($user, $project, $day, $data): MaterialUsage {
            [$timesheet] = $this->timesheets->openOrCreate($project, (int) $user->id, $day);
            Gate::forUser($user)->authorize('update', $timesheet);

            return $this->record($timesheet, $data);
        });
    }
}
