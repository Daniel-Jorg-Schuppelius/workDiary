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

use App\Models\Article\ArticleVariant;
use App\Models\Material\{Material, MaterialUsage};
use App\Models\Platform\User;
use App\Models\Project\Project;
use App\Models\Time\Timesheet;
use App\Services\Material\Contracts\MaterialStockBooking;
use App\Services\Timesheet\TimesheetResolver;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\{DB, Gate};
use Illuminate\Validation\ValidationException;

/**
 * Materialverbrauch am Stundenzettel (MVP-1594): Dialog am Zettel, API,
 * „Heute“ und Offline-Befehl `material.usage` schreiben hierüber. Leere Felder
 * kommen aus dem Materialstamm, der Preis zuerst aus der gewählten Variante
 * (MVP-1690). Ist das Material ein lagergeführter Artikel,
 * bucht der Verbrauch den Bestand mit (MVP-1679, {@see MaterialStockBooking}):
 * Anlegen, Ändern und Löschen in einer Transaktion mit der Lagerbuchung —
 * verhindert eine Lagerregel die Buchung, wird auch der Verbrauch nicht
 * gespeichert.
 */
final class MaterialUsageRecorder {
    public function __construct(
        private readonly TimesheetResolver $timesheets,
        private readonly MaterialStockBooking $stock,
    ) {}

    /** @param  array<string, mixed>  $data  geprüfte Felder des SaveMaterialUsageRequest */
    public function record(Timesheet $timesheet, array $data, User $actor): MaterialUsage {
        [$data, $allowNegative] = $this->split($data);
        if (! empty($data['material_id'])) {
            $material = Material::query()->find((int) $data['material_id']);
            if ($material instanceof Material) {
                $variant = ! empty($data['article_variant_id']) ? ArticleVariant::query()->find((int) $data['article_variant_id']) : null;
                $data['unit_price'] = $data['unit_price'] ?? MaterialUsage::defaultUnitPrice($material, $variant);
                $data['tax_rate'] = $data['tax_rate'] ?? $material->tax_rate;
                $data['unit'] = ($data['unit'] ?? '') ?: $material->unit;
                $data['description'] = ($data['description'] ?? '') ?: $material->name;
            }
        }

        // Die Tagesbuchung kennt den Zettel erst hier; der Request prüft nur mit Zettel in der Route (MVP-1627).
        if (MaterialUsage::exceedsTimesheetSum($timesheet, $data['quantity'] ?? 0, $data['unit_price'] ?? 0)) {
            throw ValidationException::withMessages(['quantity' => (string) __('Menge × Einzelpreis übersteigt die höchste speicherbare Summe des Stundenzettels.')]);
        }

        return DB::transaction(function () use ($timesheet, $data, $actor, $allowNegative): MaterialUsage {
            $usage = $timesheet->materialUsages()->create($data);
            $this->stock->sync($usage, $actor, $allowNegative);

            return $usage->refresh();
        });
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

            return $this->record($timesheet, $data, $user);
        });
    }

    /**
     * Änderung einer Zeile: eine geänderte Menge bucht die Differenz, ein
     * anderes Material oder eine andere Variante bucht die alte zurück.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(MaterialUsage $usage, array $data, User $actor): MaterialUsage {
        [$data, $allowNegative] = $this->split($data);
        // Die Variante gehört zum alten Material; ohne neue Wahl gilt die Standardvariante.
        if (array_key_exists('material_id', $data) && ! array_key_exists('article_variant_id', $data)
            && (int) ($data['material_id'] ?? 0) !== (int) ($usage->material_id ?? 0)) {
            $data['article_variant_id'] = null;
        }

        return DB::transaction(function () use ($usage, $data, $actor, $allowNegative): MaterialUsage {
            $usage->update($data);
            $this->stock->sync($usage, $actor, $allowNegative);

            return $usage->refresh();
        });
    }

    /** Löschen bucht den abgebuchten Bestand zurück. */
    public function delete(MaterialUsage $usage, ?User $actor): void {
        DB::transaction(function () use ($usage, $actor): void {
            $this->stock->release($usage, $actor);
            $usage->delete();
        });
    }

    /**
     * Ein gelöschter Stundenzettel nimmt seine Verbräuche per Fremdschlüssel
     * mit; vorher bucht jeder seinen Bestand zurück.
     */
    public function deleteTimesheet(Timesheet $timesheet, ?User $actor): void {
        DB::transaction(function () use ($timesheet, $actor): void {
            foreach ($timesheet->materialUsages()->get() as $usage) {
                $this->stock->release($usage, $actor);
            }
            $timesheet->delete();
        });
    }

    /**
     * „Negativen Bestand zulassen“ ist eine Anweisung an die Lagerbuchung,
     * kein Feld der Zeile.
     *
     * @param  array<string, mixed>  $data
     * @return array{0: array<string, mixed>, 1: bool}
     */
    private function split(array $data): array {
        $allowNegative = (bool) ($data['allow_negative'] ?? false);
        unset($data['allow_negative']);

        return [$data, $allowNegative];
    }
}
