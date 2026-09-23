<?php
/*
 * Created on   : Thu Aug 20 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ArticleMergeService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services;

use App\Enums\Inventory\StockCountStatus;
use App\Enums\Manufacturing\ManufacturingOrderStatus;
use App\Models\Article;
use Illuminate\Support\Facades\{DB, Schema};
use InvalidArgumentException;

/**
 * Führt zwei Artikel zusammen (Audit 2026-08, W2.9; Semantik und Sperren:
 * `WorkDiary-Architecture/artikel-merge-semantik.md`).
 *
 * **Kernregel — der Lagerledger wird nie umgeschrieben.** `stock_movements`
 * ist append-only, die `stock_valuation_layers` bilden die FIFO-Schichten;
 * beide dokumentieren, was tatsächlich geschah. Deshalb wandern **Varianten
 * als Ganzes** an den Ziel-Artikel (`article_variants.article_id`), und alle
 * bestandsführenden Tabellen folgen implizit, weil sie an der Variante
 * hängen. Es werden keine Bestände zusammengerechnet.
 *
 * Der Merge verweigert sich (fachliche Meldung statt Teil-Merge), wenn die
 * Zusammenführung nicht eindeutig entscheidbar wäre — siehe {@see assertMergeable()}.
 */
class ArticleMergeService extends AbstractEntityMergeService {
    /**
     * Tabellen mit zusammengesetztem Unique-Index über den Artikel:
     * Tabelle => Partnerspalte. Quell-Zeilen zu bereits belegten Partnern
     * werden verworfen (Ziel-Konditionen gewinnen).
     *
     * @var array<string, string>
     */
    private const PIVOT_TABLES = [
        'article_supplies' => 'supplier_id',
        'article_price_tiers' => 'min_qty',
        'article_units' => 'code',
        'article_option_definitions' => 'code',
    ];

    /** @var array<string, array{0: string, 1: string}> */
    private const MORPH_TABLES = [
        'attachments' => ['attachable_type', 'attachable_id'],
        'communication_notes' => ['notable_type', 'notable_id'],
        'pending_external_conflicts' => ['referenceable_type', 'referenceable_id'],
    ];

    /**
     * Felder, die — sofern beim Ziel leer — aus der Quelle übernommen werden.
     * `number`, `slug` und `gtin` bleiben außen vor (org-weit eindeutig).
     *
     * @var list<string>
     */
    private const FILLABLE_FROM_SOURCE = [
        'description', 'category', 'subcategory', 'assembly_minutes',
        'copper_weight', 'copper_base_price', 'sales_discount_group_id',
        'valuation_method', 'serial_scheme', 'default_procedure_template_version_id',
    ];

    protected function foreignKeyColumn(): string {
        return 'article_id';
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
     * Hängt Varianten und Artikel-Bezüge von $source auf $target um und
     * löscht $source.
     *
     * @param  array<string, mixed>  $fieldOverrides  Feldwerte, die unabhängig
     *         vom „leer"-Kriterium den Ziel-Wert setzen (UI-Feldauswahl).
     *
     * @throws InvalidArgumentException wenn der Merge nicht eindeutig entscheidbar ist
     */
    public function merge(Article $source, Article $target, array $fieldOverrides = []): void {
        $this->assertMergeable($source, $target);

        $morph = $source->getMorphClass();
        $sourceId = (int) $source->getKey();
        $targetId = (int) $target->getKey();

        DB::transaction(function () use ($source, $target, $sourceId, $targetId, $morph, $fieldOverrides): void {
            $this->repointed = [];
            // Varianten als Ganzes umhängen — Bestand/Bewertung/Serien folgen
            // implizit über article_variant_id, der Ledger bleibt unberührt.
            DB::table('article_variants')->where('article_id', $sourceId)->update(['article_id' => $targetId]);

            $this->repointPivots($sourceId, $targetId);
            $this->repointScalarTables($sourceId, $targetId);
            $this->repointExternalReferences($morph, $sourceId, $targetId);
            $this->repointAliases($morph, $sourceId, $targetId);
            $this->repointMorphTables($morph, $sourceId, $targetId);
            $this->repointTaggables($morph, $sourceId, $targetId);
            $this->mergeFields($source, $target, $fieldOverrides);

            $this->auditMerge($source, $target);

            $source->delete();
        });
    }

    /**
     * Sperren aus `artikel-merge-semantik.md`: lieber eine klare Absage als
     * ein Merge, der Bestände oder Kalkulationen still verfälscht.
     */
    private function assertMergeable(Article $source, Article $target): void {
        if ($source->getKey() === $target->getKey()) {
            throw new InvalidArgumentException((string) __('Quelle und Ziel dürfen nicht identisch sein.'));
        }
        if ($source->organization_id !== $target->organization_id) {
            throw new InvalidArgumentException((string) __('Artikel gehören zu unterschiedlichen Organisationen.'));
        }
        if ((string) $source->base_unit !== (string) $target->base_unit) {
            throw new InvalidArgumentException((string) __('Artikel haben unterschiedliche Basiseinheiten — Positionen und Bewertungen wären nicht vergleichbar.'));
        }
        if ((string) $source->tax_class !== (string) $target->tax_class) {
            throw new InvalidArgumentException((string) __('Artikel haben unterschiedliche Steuerklassen — bitte zuerst fachlich klären.'));
        }

        $sourceId = (int) $source->getKey();
        $targetId = (int) $target->getKey();

        // Gleiche Optionskombination auf beiden Seiten: nicht entscheidbar,
        // welcher Bestand „die" Variante ist (unique article_id+option_signature).
        $targetSignatures = DB::table('article_variants')->where('article_id', $targetId)->pluck('option_signature')->all();
        if ($targetSignatures !== []) {
            $collision = DB::table('article_variants')
                ->where('article_id', $sourceId)
                ->whereIn('option_signature', $targetSignatures)
                ->exists();
            if ($collision) {
                throw new InvalidArgumentException((string) __('Beide Artikel haben eine Variante mit derselben Optionskombination — Bestände dürfen nicht vermischt werden. Bitte zuerst manuell auflösen (z. B. per Umlagerung).'));
            }
        }

        // Laufende Inventur auf einer betroffenen Variante: die Zählgrundlage
        // würde unter der Erfassung wegwandern.
        if (Schema::hasTable('stock_counts') && Schema::hasTable('stock_count_lines')) {
            $openCount = DB::table('stock_count_lines')
                ->join('stock_counts', 'stock_counts.id', '=', 'stock_count_lines.stock_count_id')
                ->join('article_variants', 'article_variants.id', '=', 'stock_count_lines.article_variant_id')
                ->where('article_variants.article_id', $sourceId)
                ->whereIn('stock_counts.status', [StockCountStatus::Counting->value, StockCountStatus::Review->value])
                ->exists();
            if ($openCount) {
                throw new InvalidArgumentException((string) __('Für diesen Artikel läuft eine Inventur — Zusammenführen erst nach deren Abschluss.'));
            }
        }

        // Offener Fertigungsauftrag: Rezeptur/Materialbedarf beziehen sich auf
        // den Artikel; ein Wechsel mitten im Auftrag verfälscht die Nachkalkulation.
        if (Schema::hasTable('manufacturing_orders')) {
            $openOrder = DB::table('manufacturing_orders')
                ->where('article_id', $sourceId)
                ->whereIn('status', [
                    ManufacturingOrderStatus::Released->value,
                    ManufacturingOrderStatus::InProgress->value,
                    ManufacturingOrderStatus::Waiting->value,
                ])
                ->exists();
            if ($openOrder) {
                throw new InvalidArgumentException((string) __('Für diesen Artikel läuft ein Fertigungsauftrag — Zusammenführen erst nach dessen Abschluss.'));
            }
        }
    }
}
