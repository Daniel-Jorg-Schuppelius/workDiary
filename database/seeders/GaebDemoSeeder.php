<?php
/*
 * Created on   : Sun Jun 28 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : GaebDemoSeeder.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Database\Seeders;

use App\Enums\Gaeb\{BoqItemStatus, BoqProgressSource, GaebPhase};
use App\Models\{BillOfQuantity, BoqItem, Organization};
use App\Services\Gaeb\{BoqExportService, BoqProgressService, BoqWorkflowService, GaebImportService};
use Illuminate\Database\Seeder;

/**
 * GAEB-Demo-/Beispieldaten (Feature 049, MVP-086): importiert das Beispiel-LV,
 * meldet Aufmaß, ergänzt einen Nachtrag und erzeugt einen Export — damit der
 * vollständige Bau-/Ausbau-Ablauf (Import → Ausführung → Nachtrag →
 * Nachkalkulation → Export) in Demos und Tests reproduzierbar ist.
 */
class GaebDemoSeeder extends Seeder {
    public function run(?Organization $organization = null): void {
        $organization ??= Organization::query()->orderBy('id')->first();
        if ($organization === null) {
            return;
        }

        app()->instance('currentOrganization', $organization);

        $xml = (string) file_get_contents(base_path('tests/Fixtures/gaeb/sample_x86.xml'));

        $import = app(GaebImportService::class)->import($xml, 'demo-lv.x86', $organization->id, [
            'name' => 'Demo-LV Neubau Lagerhalle',
        ]);

        if ($import->bill_of_quantity_id === null) {
            return;
        }

        /** @var BillOfQuantity $boq */
        $boq = BillOfQuantity::query()->findOrFail($import->bill_of_quantity_id);

        // Aufmaß auf zwei Positionen (Teilausführung).
        $progress = app(BoqProgressService::class);
        $first = $boq->items()->where('reference_no', '01.0010')->first();
        if ($first instanceof BoqItem) {
            $progress->record($first, '60.000', ['source' => BoqProgressSource::Measurement, 'note' => 'Erstes Aufmaß']);
        }
        $second = $boq->items()->where('reference_no', '02.0010')->first();
        if ($second instanceof BoqItem) {
            $progress->record($second, '250.000', ['source' => BoqProgressSource::Measurement]);
            $second->forceFill(['status' => BoqItemStatus::Completed])->save();
        }

        // Nachtrag als eigener Vorgang.
        $workflow = app(BoqWorkflowService::class);
        if (!$boq->items()->where('reference_no', 'N01')->exists()) {
            $workflow->createAddendum($boq, [
                'reference_no' => 'N01',
                'short_text' => 'Nachtrag: zusätzliche Entwässerungsrinne',
                'quantity' => '15.000',
                'unit' => 'm',
                'unit_price' => '34.00',
            ]);
        }

        // Feature 109: Der Kostengruppenkatalog kommt mit der Datei; eine
        // Vorschlagsregel füllt die Positionen, die keine eigene Zuordnung
        // tragen — genau der Ablauf, den ein Betrieb geht.
        $this->seedCatalogRule($organization);
        app(\App\Services\Gaeb\CatalogSuggestionService::class)->apply($boq->fresh() ?? $boq);

        // LV in Ausführung setzen und einen Export protokollieren.
        if ($workflow->canTransition($boq->status, BoqItemStatus::Ordered)) {
            $workflow->transitionBill($boq, BoqItemStatus::Ordered);
        }
        app(BoqExportService::class)->export($boq->fresh() ?? $boq, GaebPhase::Award);
    }

    /**
     * Eine Beispielregel: Erdarbeiten schlagen auf die Baugrube (KG 310).
     *
     * Der Leistungsbereich ist die verlässlichere Grundlage als ein Stichwort
     * — er steht in der Datei. Die Regel greift nur, wo die Position keine
     * eigene Zuordnung mitbringt.
     */
    private function seedCatalogRule(Organization $organization): void {
        $registry = \App\Models\Catalog\CatalogRegistry::query()
            ->where('key', 'din276-2018')
            ->first();
        if ($registry === null) {
            // Ohne Katalogstamm gäbe es nichts, worauf die Regel zeigen könnte.
            return;
        }

        \App\Models\Catalog\CatalogAssignmentRule::query()->firstOrCreate(
            [
                'organization_id' => $organization->id,
                'match_type' => \App\Models\Catalog\CatalogAssignmentRule::MATCH_WORK_CATEGORY,
                'match_value' => '002',
            ],
            [
                'catalog_registry_id' => $registry->id,
                'code' => '310',
                'priority' => 100,
                'active' => true,
            ],
        );
    }
}
