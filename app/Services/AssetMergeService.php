<?php
/*
 * Created on   : Tue Jul 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AssetMergeService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services;

use App\Enums\Asset\AssetOwnership;
use App\Models\Asset;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Führt zwei (doppelt angelegte) Assets zusammen: hängt alle abhängigen
 * Datensätze aufs Ziel um und löscht die Quelle. Externe Referenzen mit
 * Primär-Kollision (z. B. zweite AnyDesk-Geräte-ID) landen als Alias aufs
 * Ziel — Folgeimporte mit dem alten Schlüssel lösen direkt auf. Gebuchte
 * Zeiteinträge sind nicht asset-gebunden und bleiben unberührt.
 */
class AssetMergeService extends AbstractEntityMergeService {
    /**
     * Pivot-Tabellen mit Unique-Index (Partner + asset_id): bereits am Ziel
     * bestehende Zuordnungen werden vor dem Umhängen dedupliziert.
     *
     * @var array<string, string>
     */
    private const PIVOT_TABLES = [
        'change_asset' => 'change_id',
        'asset_finance_contract_assets' => 'asset_finance_contract_id',
        'rental_case_assets' => 'rental_case_id',
        'asset_compliance_assignments' => 'asset_compliance_profile_id',
    ];

    /**
     * Polymorphe Tabellen, deren Zeilen auf ein Asset zeigen können.
     *
     * @var array<string, array{0: string, 1: string}>
     */
    private const MORPH_TABLES = [
        'communication_notes' => ['notable_type', 'notable_id'],
        'attachments' => ['attachable_type', 'attachable_id'],
        'pending_external_conflicts' => ['referenceable_type', 'referenceable_id'],
        'maintenance_plans' => ['subject_type', 'subject_id'],
    ];

    /**
     * Felder, die — sofern beim Ziel leer — aus der Quelle übernommen werden.
     *
     * @var list<string>
     */
    private const FILLABLE_FROM_SOURCE = [
        'manufacturer', 'model', 'product_id', 'serial_no', 'inventory_no',
        'customer_id', 'foreign_customer_id', 'room_id',
        'location_text', 'location_lat', 'location_lng',
        'commissioned_on', 'warranty_until',
        'next_maintenance_on', 'next_inspection_on',
        'notes', 'custom',
    ];

    protected function foreignKeyColumn(): string {
        return 'asset_id';
    }

    protected function morphTables(): array {
        return self::MORPH_TABLES;
    }

    protected function pivotTables(): array {
        return self::PIVOT_TABLES;
    }

    protected function fillableFromSource(): array {
        return self::FILLABLE_FROM_SOURCE;
    }

    /**
     * Hängt alle Daten von $source auf $target um und löscht $source.
     *
     * @param  array<string, mixed>  $fieldOverrides  Feldwerte, die unabhängig
     *         vom „leer"-Kriterium den Ziel-Wert setzen (UI-Feldauswahl).
     */
    public function merge(Asset $source, Asset $target, array $fieldOverrides = []): void {
        if ($source->getKey() === $target->getKey()) {
            throw new InvalidArgumentException('Quelle und Ziel dürfen nicht identisch sein.');
        }
        if ((int) $source->organization_id !== (int) $target->organization_id) {
            throw new InvalidArgumentException('Assets gehören zu unterschiedlichen Organisationen.');
        }

        $morph = $source->getMorphClass();
        $sourceId = (int) $source->getKey();
        $targetId = (int) $target->getKey();

        DB::transaction(function () use ($source, $target, $sourceId, $targetId, $morph, $fieldOverrides): void {
            $this->repointed = [];
            // Wartungspläne tragen einen je Anlage eindeutigen Code
            // (`UNIQUE(asset_id, code)`). Zwei Anlagen haben erfahrungsgemäß
            // beide einen Plan „JAHRESWARTUNG" — das darf den Merge nicht
            // scheitern lassen, also Code eindeutig machen statt abbrechen.
            $this->uniquifyChildColumn('maintenance_plans', 'code', $sourceId, $targetId);

            $this->repointScalarTables($sourceId, $targetId);
            $this->repointPivots($sourceId, $targetId);
            $this->repointExternalReferences($morph, $sourceId, $targetId);
            $this->repointAliases($morph, $sourceId, $targetId);
            $this->repointMorphTables($morph, $sourceId, $targetId);
            $this->repointTaggables($morph, $sourceId, $targetId);
            $this->mergeFields($source, $target, $fieldOverrides);

            $this->auditMerge($source, $target);

            // Hartes Löschen (Refs bereits umgehängt). Über das Modell, damit
            // der Audit-Log „deleted" festhält.
            $source->delete();
        });
    }

    /**
     * Konsistenz nach dem Feld-Auffüllen: Kunde gesetzt → Kundengerät;
     * Mehrkundengerät-Flag bleibt erhalten, wenn eine Seite es trug.
     */
    protected function mergeEntitySpecificFields(Model $source, Model $target): void {
        if (! $source instanceof Asset || ! $target instanceof Asset) {
            return;
        }

        if ($source->shared_remote && ! $target->shared_remote) {
            $target->shared_remote = true;
        }

        if ($target->customer_id !== null && $target->owned_by === AssetOwnership::Organization) {
            $target->owned_by = AssetOwnership::Customer;
        }
        if ($target->customer_id === null) {
            // Ohne Kunde darf kein Fremdkunde übrig bleiben.
            $target->foreign_customer_id = null;
        } elseif ($target->foreign_customer_id !== null) {
            // Übernommener Fremdkunde muss zum Ziel-Kunden gehören.
            $belongsToCustomer = \App\Models\ForeignCustomer::query()
                ->whereKey($target->foreign_customer_id)
                ->where('customer_id', $target->customer_id)
                ->exists();
            if (! $belongsToCustomer) {
                $target->foreign_customer_id = null;
            }
        }
    }
}
