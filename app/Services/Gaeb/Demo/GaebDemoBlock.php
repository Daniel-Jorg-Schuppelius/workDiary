<?php
/*
 * Created on   : Mon Nov 09 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : GaebDemoBlock.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Gaeb\Demo;

use App\Enums\Gaeb\{BoqItemStatus, GaebImportStatus};
use App\Models\Gaeb\{BillOfQuantity, BoqCatalog, BoqCatalogAssignment, BoqChangeOrder, BoqCostType, BoqExport, BoqItem, BoqItemCostApproach, BoqItemProgress, BoqItemQuantitySplit, BoqSection, GaebImport};
use App\Models\Platform\Organization;
use App\Models\Project\Project;
use App\Services\Demo\Contracts\{DemoBlock, DemoSeedContext};
use App\Services\Gaeb\{BoqProgressService, BoqWorkflowService, GaebImportService};
use CommonToolkit\Enums\CurrencyCode;
use CommonToolkit\ValueObjects\Money;
use ERechnungToolkit\Entities\Gaeb\{GaebBoq, GaebItem, GaebSection};
use ERechnungToolkit\Enums\{GaebFormat, GaebPhase};
use ERechnungToolkit\Generators\GaebWriter;
use RuntimeException;

/**
 * Bau & GAEB zur Vorführung (MVP-1533): ein Leistungsverzeichnis zum
 * Hauptprojekt mit dem Material der Musterbranche als Lieferpositionen und
 * Montage als Arbeitsposition. Es kommt als GAEB-DA-XML-Auftrag (X86) über den
 * normalen Import herein, wird beauftragt und hat ein erstes Aufmaß.
 */
final class GaebDemoBlock implements DemoBlock {
    /** Menge je Materialposition, in Blueprint-Reihenfolge. */
    private const array QUANTITIES = ['4', '120', '6'];

    public function __construct(
        private readonly GaebWriter $writer,
        private readonly GaebImportService $imports,
        private readonly BoqWorkflowService $workflow,
        private readonly BoqProgressService $progress,
    ) {}

    public function supports(DemoSeedContext $context): bool {
        return true;
    }

    public function seed(DemoSeedContext $context): array {
        $admin = $context->users->first();
        /** @var Project|null $project */
        $project = $context->projects->first();
        if (! $context->moduleActive('module.bau') || $admin === null || $project === null) {
            return ['bills_of_quantities' => 0];
        }

        /** @var list<array{sku: string, name: string, unit: string, price: string}> $materials */
        $materials = $context->blueprint['materials'];
        $items = [];
        foreach ($materials as $index => $material) {
            $items[] = new GaebItem(
                sprintf('01.%04d', ($index + 1) * 10),
                '01',
                shortText: $material['name'],
                quantity: self::QUANTITIES[$index % count(self::QUANTITIES)],
                unit: $material['unit'],
                unitPrice: Money::of($material['price'], CurrencyCode::Euro, 4),
                position: $index + 1,
            );
        }
        $items[] = new GaebItem('02.0010', '02', shortText: (string) __('demo.bau.item_assembly'), longText: (string) __('demo.bau.item_assembly_long'), quantity: '16', unit: 'h', unitPrice: Money::of('68.00', CurrencyCode::Euro, 4), position: 1);
        $items[] = new GaebItem('02.0020', '02', shortText: (string) __('demo.bau.item_hourly'), quantity: '4', unit: 'h', unitPrice: Money::of('58.00', CurrencyCode::Euro, 4), hourlyItem: true, position: 2);

        $document = new GaebBoq(
            projectName: $project->name,
            sections: [
                new GaebSection('01', label: (string) __('demo.bau.section_supply'), position: 1),
                new GaebSection('02', label: (string) __('demo.bau.section_labour'), position: 2),
            ],
            items: $items,
        );
        $file = $this->writer->write($document, GaebFormat::DaXml, GaebPhase::Award);

        $import = $this->imports->import($file['content'], 'demo-leistungsverzeichnis.x86', $context->organization->id, [
            'project_id' => $project->id,
            'created_by' => $admin->id,
            'name' => (string) __('demo.bau.boq_name', ['project' => $project->name]),
        ]);
        $boq = $import->bill_of_quantity_id !== null ? BillOfQuantity::query()->find($import->bill_of_quantity_id) : null;
        if ($import->status !== GaebImportStatus::Imported || ! $boq instanceof BillOfQuantity) {
            throw new RuntimeException('Demo-Seeder: Leistungsverzeichnis ließ sich nicht importieren.');
        }

        $this->workflow->transitionBill($boq, BoqItemStatus::Ordered);
        $first = $boq->items()->where('reference_no', '01.0010')->first();
        if ($first instanceof BoqItem) {
            $this->progress->recordMeasured($first, '2', $context->mainDiary?->id, (string) __('demo.bau.progress_note'), $admin->id);
        }

        return ['bills_of_quantities' => 1];
    }

    public function purge(Organization $organization): void {
        $boqIds = BillOfQuantity::query()->withoutGlobalScopes()->where('organization_id', $organization->id)->pluck('id');
        $itemIds = BoqItem::query()->withoutGlobalScopes()->whereIn('bill_of_quantity_id', $boqIds)->pluck('id');
        BoqItemProgress::query()->withoutGlobalScopes()->whereIn('boq_item_id', $itemIds)->delete();
        BoqItemQuantitySplit::query()->withoutGlobalScopes()->whereIn('boq_item_id', $itemIds)->delete();
        BoqItemCostApproach::query()->withoutGlobalScopes()->whereIn('boq_item_id', $itemIds)->delete();
        BoqCatalogAssignment::query()->withoutGlobalScopes()->whereIn('bill_of_quantity_id', $boqIds)->delete();
        BoqItem::query()->withoutGlobalScopes()->whereKey($itemIds)->delete();
        BoqSection::query()->withoutGlobalScopes()->whereIn('bill_of_quantity_id', $boqIds)->whereNotNull('parent_id')->delete();
        BoqSection::query()->withoutGlobalScopes()->whereIn('bill_of_quantity_id', $boqIds)->delete();
        foreach ([BoqCatalog::class, BoqChangeOrder::class, BoqCostType::class, BoqExport::class] as $model) {
            $model::query()->withoutGlobalScopes()->whereIn('bill_of_quantity_id', $boqIds)->delete();
        }
        GaebImport::query()->withoutGlobalScopes()->where('organization_id', $organization->id)->delete();
        BillOfQuantity::query()->withoutGlobalScopes()->whereKey($boqIds)->delete();
    }
}
