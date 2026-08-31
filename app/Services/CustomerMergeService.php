<?php
/*
 * Created on   : Mon Jun 29 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CustomerMergeService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services;

use App\Models\Customer;
use Illuminate\Support\Facades\{DB, Schema};
use InvalidArgumentException;

/**
 * Führt zwei lokale Kunden-Datensätze zusammen (Dubletten-Bereinigung, z. B.
 * nach dem Toggl-Import). Alle abhängigen Datensätze werden vom Quell- auf den
 * Ziel-Kunden umgehängt, leere Ziel-Felder aus der Quelle aufgefüllt, der
 * Quell-Kunde anschließend hart gelöscht. Umhäng-Kern siehe
 * {@see AbstractEntityMergeService}.
 *
 * Mandanten-/Sicherheits-Garantie: Quelle und Ziel müssen zur selben
 * Organisation gehören und dürfen nicht identisch sein.
 *
 * Kollisionsbehandlung:
 *  - projects (customer_id, slug) und sites (customer_id, code) tragen
 *    zusammengesetzte Unique-Indizes — kollidierende Quell-Slugs/-Codes werden
 *    vor dem Umhängen eindeutig gemacht.
 *  - external_references trägt einen Unique-Index über
 *    (plugin_id, external_type, referenceable_type, referenceable_id);
 *    kollidierende Quell-Referenzen werden verworfen (Ziel gewinnt).
 *  - taggables hat den Primärschlüssel (tag_id, taggable_id, taggable_type);
 *    Tags, die das Ziel bereits trägt, werden nicht doppelt umgehängt.
 */
class CustomerMergeService extends AbstractEntityMergeService {
    /**
     * Polymorphe Tabellen, deren Zeilen auf den Kunden zeigen können
     * (type-Spalte => id-Spalte). Werden umgehängt, wo der morph-Type auf
     * Customer zeigt. Keine eigenen Unique-Indizes → einfacher Bulk-UPDATE.
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
     *
     * @var list<string>
     */
    private const FILLABLE_FROM_SOURCE = [
        'company', 'vat_id', 'tax_number', 'lexoffice_contact_number',
        'contact_name', 'contact_persons', 'email', 'phone', 'mobile', 'fax',
        'homepage', 'address', 'address_street', 'address_zip', 'address_city',
        'country', 'timezone', 'color', 'hourly_rate', 'internal_rate', 'comment',
        'invoice_text', 'bank_account_holder', 'bank_iban', 'bank_bic', 'bank_name',
        'buyer_reference', 'debtor_no',
    ];

    protected function foreignKeyColumn(): string {
        return 'customer_id';
    }

    /**
     * `projects` und `sites` laufen über eigene Schritte: dort muss vor dem
     * Umhängen der Slug bzw. Code eindeutig gemacht und das Standardprojekt
     * entwertet werden.
     *
     * @return list<string>
     */
    protected function separatelyHandledTables(): array {
        return ['projects', 'sites'];
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
    public function merge(Customer $source, Customer $target, array $fieldOverrides = []): void {
        if ($source->getKey() === $target->getKey()) {
            throw new InvalidArgumentException('Quelle und Ziel dürfen nicht identisch sein.');
        }
        if ($source->organization_id !== $target->organization_id) {
            throw new InvalidArgumentException('Kunden gehören zu unterschiedlichen Organisationen.');
        }

        $morph = $source->getMorphClass();
        $sourceId = (int) $source->getKey();
        $targetId = (int) $target->getKey();

        DB::transaction(function () use ($source, $target, $sourceId, $targetId, $morph, $fieldOverrides): void {
            $this->repointed = [];
            $this->repointProjects($sourceId, $targetId);
            $this->repointSites($sourceId, $targetId);
            $this->repointScalarTables($sourceId, $targetId);
            $this->repointExternalReferences($morph, $sourceId, $targetId);
            $this->repointAliases($morph, $sourceId, $targetId);
            $this->repointMorphTables($morph, $sourceId, $targetId);
            $this->repointTaggables($morph, $sourceId, $targetId);
            $this->mergeFields($source, $target, $fieldOverrides);

            $this->auditMerge($source, $target);

            // Hartes Löschen ohne DeletePolicy-Guard (Projekte/Refs bereits umgehängt). Quell-Kunde verschwindet
            // endgültig; der Audit-Log hält „deleted" fest.
            $source->delete();
        });
    }

    private function repointProjects(int $sourceId, int $targetId): void {
        if (! Schema::hasTable('projects') || ! Schema::hasColumn('projects', 'customer_id')) {
            return;
        }

        // Slug-Kollisionen auflösen (zusammengesetzter Unique-Index customer_id+slug).
        if (Schema::hasColumn('projects', 'slug')) {
            $this->uniquifyChildColumn('projects', 'slug', $sourceId, $targetId);
        }

        // Hat das Ziel bereits ein Standardprojekt, verlieren die Quell-Defaults ihren Default-Status (nur eines erlaubt).
        if (Schema::hasColumn('projects', 'is_default')) {
            $targetHasDefault = DB::table('projects')
                ->where('customer_id', $targetId)->where('is_default', true)->exists();
            if ($targetHasDefault) {
                DB::table('projects')
                    ->where('customer_id', $sourceId)->where('is_default', true)
                    ->update(['is_default' => false]);
            }
        }

        DB::table('projects')->where('customer_id', $sourceId)->update(['customer_id' => $targetId]);
    }

    private function repointSites(int $sourceId, int $targetId): void {
        if (! Schema::hasTable('sites') || ! Schema::hasColumn('sites', 'customer_id')) {
            return;
        }

        // Code-Kollisionen auflösen (zusammengesetzter Unique-Index customer_id+code).
        if (Schema::hasColumn('sites', 'code')) {
            $this->uniquifyChildColumn('sites', 'code', $sourceId, $targetId);
        }

        DB::table('sites')->where('customer_id', $sourceId)->update(['customer_id' => $targetId]);
    }
}
