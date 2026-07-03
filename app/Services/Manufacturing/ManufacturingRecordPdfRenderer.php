<?php
/*
 * Created on   : Fri Jul 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ManufacturingRecordPdfRenderer.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Manufacturing;

use App\Models\{ManufacturingOrder, Organization, User};
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\View;

/**
 * Rendert den Fertigungsnachweis eines Auftrags als PDF (Feature 047,
 * MVP-065): Kopfdaten mit eingefrorenem Arbeitsplan, alle Teilrückmeldungen
 * (Gut/Ausschuss/Nacharbeit), Materialpositionen mit Ist-Verbrauch und
 * Ist-Kosten sowie die Qualitätskennzahlen. Reiner Nachweisbeleg — kein
 * Faktura-Dokument.
 */
class ManufacturingRecordPdfRenderer {
    public function __construct(private readonly ManufacturingQualityService $quality) {}

    public function render(ManufacturingOrder $order): string {
        $order->loadMissing(['article', 'variant', 'warehouse', 'materials', 'reports', 'procedureRun.templateVersion.template']);
        $organization = Organization::query()->withoutGlobalScopes()->find($order->organization_id);

        $reporterIds = $order->reports->pluck('reported_by')->filter()->unique()->all();
        $reporters = $reporterIds === []
            ? collect()
            : User::query()->withoutGlobalScopes()->whereIn('id', $reporterIds)->pluck('name', 'id');

        $html = View::make('pdf.manufacturing-record', [
            'order' => $order,
            'organization' => $organization,
            'number' => $this->number($order),
            'quality' => $this->quality->metricsFor($order),
            'reporters' => $reporters,
            'generatedAt' => now(),
        ])->render();

        /** @var \Barryvdh\DomPDF\PDF $pdf */
        $pdf = Pdf::loadHTML($html)->setPaper('a4');

        return (string) $pdf->output();
    }

    /** Nachweis-Nummer (stabil aus der Auftrags-ID abgeleitet). */
    public function number(ManufacturingOrder $order): string {
        return 'FN-' . str_pad((string) $order->id, 6, '0', STR_PAD_LEFT);
    }
}
