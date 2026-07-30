<?php
/*
 * Created on   : Sun Jul 20 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : BindsTimeImportReference.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Import\Specs\Concerns;

use App\Models\{ExternalReference, Organization};
use CommonToolkit\Enums\HashAlgorithm;
use CommonToolkit\Helper\Data\CryptoHelper;
use Illuminate\Database\Eloquent\Model;

/**
 * Idempotenz für Zeiterfassungs-Importe (MVP-438).
 *
 * Weder {@see \App\Models\Attendance} noch {@see \App\Models\TimeEntry} tragen
 * eine `external_id`-Spalte — die Wiederhol-Erkennung läuft daher über eine
 * {@see ExternalReference}-Bindung (Muster der Plugin-Zeitimporte). Als stabiler
 * Schlüssel dient die iCal-`UID`/CSV-`external_id`; fehlt sie, ein
 * deterministischer Hash über den fachlichen Schlüssel der Zeile.
 *
 * Bewusste Vereinfachung ggü. Feature-Doc 094: **eine** Plugin-Kennung
 * (`time-import`) statt getrennter `csv-import`/`ical-import` — die Spec ist
 * format-neutral (kennt die Quelle nicht), und die Fremd-ID ist je Ereignis
 * über beide Formate identisch, sodass die Dedup-Bindung stabil bleibt.
 */
trait BindsTimeImportReference {
    private const TIME_IMPORT_PLUGIN = 'time-import';

    /**
     * Stabiler Idempotenz-Schlüssel: gesetzte Fremd-ID (`ext:…`) oder Hash des
     * fachlichen Schlüssels (`nat:…`).
     */
    protected function importKey(?string $externalId, string $naturalKey): string {
        $externalId = $externalId !== null ? trim($externalId) : '';

        return $externalId !== ''
            ? 'ext:' . $externalId
            : 'nat:' . CryptoHelper::hash($naturalKey, HashAlgorithm::SHA1);
    }

    /**
     * Findet den bereits importierten Datensatz zu einem Schlüssel.
     *
     * @param  class-string<Model>  $modelClass
     */
    protected function findImported(Organization $organization, string $modelClass, string $externalType, string $key): ?Model {
        $ref = ExternalReference::query()
            ->forPlugin($organization, self::TIME_IMPORT_PLUGIN, $externalType)
            ->where('referenceable_type', (new $modelClass)->getMorphClass())
            ->forExternalId($key)
            ->first();

        return $ref?->referenceable;
    }

    /**
     * Schreibt/aktualisiert die Fremd-ID-Bindung eines importierten Datensatzes.
     */
    protected function bindImported(Organization $organization, Model $model, string $externalType, string $key): void {
        ExternalReference::query()->updateOrCreate(
            [
                'plugin_id' => self::TIME_IMPORT_PLUGIN,
                'external_type' => $externalType,
                'referenceable_type' => $model->getMorphClass(),
                'referenceable_id' => $model->getKey(),
            ],
            [
                'organization_id' => $organization->id,
                'external_id' => $key,
                'synced_at' => now(),
            ],
        );
    }
}
