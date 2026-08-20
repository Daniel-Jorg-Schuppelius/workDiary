<?php
/*
 * Created on   : Thu Aug 20 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SupplierMergeService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services;

use App\Models\Supplier;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Führt zwei lokale Lieferanten-Datensätze zusammen (Audit 2026-08, W2.3).
 * Alle abhängigen Datensätze werden vom Quell- auf den Ziel-Lieferanten
 * umgehängt, leere Ziel-Felder aus der Quelle aufgefüllt, der Quell-Lieferant
 * anschließend hart gelöscht. Umhäng-Kern siehe
 * {@see AbstractEntityMergeService}.
 *
 * Mandanten-/Sicherheits-Garantie: Quelle und Ziel müssen zur selben
 * Organisation gehören und dürfen nicht identisch sein.
 *
 * Kollisionsbehandlung:
 *  - `article_supplies` trägt den Unique-Index (organization_id, article_id,
 *    supplier_id): liefern beide Lieferanten denselben Artikel, würde das
 *    Umhängen den Index verletzen — die Quell-Zeile wird deshalb verworfen
 *    (Ziel-Konditionen gewinnen). Läuft über {@see pivotTables()}.
 *  - `external_references` trägt einen Unique-Index über
 *    (plugin_id, external_type, referenceable_type, referenceable_id);
 *    kollidierende Quell-Referenzen werden verworfen (Ziel gewinnt) und als
 *    Alias festgehalten, damit ein Re-Import auf das Ziel zeigt.
 *  - `taggables` hat den Primärschlüssel (tag_id, taggable_id, taggable_type);
 *    Tags, die das Ziel bereits trägt, werden nicht doppelt umgehängt.
 *
 * Bewusst NICHT umgehängt: nichts. Anders als beim Artikel-Merge (Lager-Ledger)
 * gibt es beim Lieferanten keine append-only-Historie, die eine Zuordnung
 * einfrieren müsste — Bestellungen, Eingangsrechnungen und Bewertungen zeigen
 * schlicht auf den überlebenden Datensatz.
 */
class SupplierMergeService extends AbstractEntityMergeService {
    /**
     * Tabellen mit direkter `supplier_id`-Spalte (Bulk-UPDATE, kein
     * supplier-bezogener Unique-Index). Vollständige Inventur gegen das
     * Schema-Dump, Stand W2.3.
     *
     * @var list<string>
     */
    private const SUPPLIER_ID_TABLES = [
        'asset_finance_contracts',
        'claim_cases',
        'claim_supplier_recourses',
        'contracts',
        'investment_options',
        'isms_supplier_assessments',
        'lexoffice_vouchers',
        'pricing_change_alerts',
        'pricing_margin_rules',
        'purchase_orders',
        'supplier_catalog_items',
        'supplier_catalog_sources',
    ];

    /**
     * Tabellen mit zusammengesetztem Unique-Index über den Lieferanten:
     * Tabelle => Partnerspalte. Quell-Zeilen zu bereits belegten Partnern
     * werden verworfen statt umgehängt.
     *
     * @var array<string, string>
     */
    private const PIVOT_TABLES = [
        'article_supplies' => 'article_id',
    ];

    /**
     * Polymorphe Tabellen, deren Zeilen auf den Lieferanten zeigen können
     * (type-Spalte => id-Spalte).
     *
     * @var array<string, array{0: string, 1: string}>
     */
    private const MORPH_TABLES = [
        'contact_addresses' => ['addressable_type', 'addressable_id'],
        'contact_bank_accounts' => ['accountable_type', 'accountable_id'],
        'communication_notes' => ['notable_type', 'notable_id'],
        'attachments' => ['attachable_type', 'attachable_id'],
        'pending_external_conflicts' => ['referenceable_type', 'referenceable_id'],
    ];

    /**
     * Felder, die — sofern beim Ziel leer — aus der Quelle übernommen werden.
     * `number`/`slug` bleiben außen vor (Org-weit unique, Ziel behält seine).
     *
     * @var list<string>
     */
    private const FILLABLE_FROM_SOURCE = [
        'company', 'vat_id', 'tax_number', 'vendor_number',
        'contact_name', 'contact_persons', 'email', 'phone', 'mobile', 'fax',
        'homepage', 'address', 'address_street', 'address_zip', 'address_city',
        'address_lat', 'address_lng', 'country', 'currency', 'timezone', 'color',
        'comment', 'bank_account_holder', 'bank_iban', 'bank_bic', 'bank_name',
    ];

    protected function foreignKeyColumn(): string {
        return 'supplier_id';
    }

    protected function scalarTables(): array {
        return self::SUPPLIER_ID_TABLES;
    }

    protected function pivotTables(): array {
        return self::PIVOT_TABLES;
    }

    protected function morphTables(): array {
        return self::MORPH_TABLES;
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
    public function merge(Supplier $source, Supplier $target, array $fieldOverrides = []): void {
        if ($source->getKey() === $target->getKey()) {
            throw new InvalidArgumentException('Quelle und Ziel dürfen nicht identisch sein.');
        }
        if ($source->organization_id !== $target->organization_id) {
            throw new InvalidArgumentException('Lieferanten gehören zu unterschiedlichen Organisationen.');
        }

        $morph = $source->getMorphClass();
        $sourceId = (int) $source->getKey();
        $targetId = (int) $target->getKey();

        DB::transaction(function () use ($source, $target, $sourceId, $targetId, $morph, $fieldOverrides): void {
            $this->repointPivots($sourceId, $targetId);
            $this->repointScalarTables($sourceId, $targetId);
            $this->repointExternalReferences($morph, $sourceId, $targetId);
            $this->repointAliases($morph, $sourceId, $targetId);
            $this->repointMorphTables($morph, $sourceId, $targetId);
            $this->repointTaggables($morph, $sourceId, $targetId);
            $this->mergeFields($source, $target, $fieldOverrides);

            // Hartes Löschen (alle Bezüge sind umgehängt); der Audit-Log hält „deleted" fest.
            $source->delete();
        });
    }
}
