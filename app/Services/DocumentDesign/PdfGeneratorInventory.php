<?php
/*
 * Created on   : Mon Aug 18 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PdfGeneratorInventory.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\DocumentDesign;

use App\Enums\DocumentDesign\RenderDocumentKind;

/**
 * Zentrales Inventar aller serverseitigen PDF-Generatoren (Issue #83):
 * Jede Aufrufstelle der Render-Pipeline ist hier mit ihren registrierten
 * Dokumentarten erfasst; direkte PDF-Writer-Aufrufe außerhalb der Pipeline
 * sind ausdrücklich begründete Ausnahmen. Der Architekturtest
 * {@see \Tests\Unit\Architecture\PdfGeneratorRegistryRuleTest} gleicht dieses
 * Inventar gegen den Code ab — ein neuer PDF-Generator kann die Registrierung
 * damit nicht unbemerkt umgehen.
 *
 * Die Dokumentart-Metadaten (Familie, Seitenformat, Pflichtblöcke,
 * Design-Fähigkeit) trägt {@see RenderDocumentKind} selbst.
 */
final class PdfGeneratorInventory {
    /**
     * Generatoren auf der Design-Pipeline (`DocumentDesignRenderer::renderPdf`):
     * Datei (repo-relativ) → dort registrierte Dokumentarten.
     *
     * @var array<string, array<int, string>>
     */
    public const GENERATORS = [
        // Vertrieb/Fakturierung
        'app/Services/Invoicing/InvoicePdfRenderer.php' => ['invoice', 'credit_note', 'proforma_invoice'],
        'app/Services/Invoicing/QuotePdfRenderer.php' => ['quote'],
        'app/Services/Invoicing/OrderConfirmationPdfRenderer.php' => ['order_confirmation'],
        'app/Services/Invoicing/DunningPdfRenderer.php' => ['dunning'],
        // Einkauf/Logistik
        'app/Services/Procurement/PurchaseOrderPdfRenderer.php' => ['purchase_order'],
        'app/Services/Manufacturing/DeliveryNotePdfRenderer.php' => ['delivery_note'],
        // Kommissionierliste (Feature 048, MVP-706): interner Arbeitsbeleg.
        'app/Services/Inventory/PickListPdfRenderer.php' => ['report'],
        // Leistung/Nachweis
        'app/Services/Protocol/ProtocolPdfRenderer.php' => ['protocol'],
        'app/Services/Disposal/DisposalRecordPdfRenderer.php' => ['protocol'],
        'app/Services/Manufacturing/ManufacturingRecordPdfRenderer.php' => ['manufacturing_record'],
        // VOB/B-Schreiben (Feature 062, MVP-728): der Renderer bedient beide
        // Arten, die Belegart kommt aus dem Schreiben selbst.
        'app/Services/Construction/ConstructionNoticePdfRenderer.php' => ['construction_obstruction_notice', 'construction_concern_notice'],
        // Lernplattform (Feature 149): Teilnahmenachweis und Nachweismappe.
        'app/Services/Learning/LearningCertificatePdfRenderer.php' => ['certificate'],
        'app/Services/Learning/LearningDossierPdfRenderer.php' => ['report'],
        'app/Services/Learning/LearningAttendanceListPdfRenderer.php' => ['report'],
        'app/Services/Timesheet/PdfRenderer.php' => ['timesheet'],
        'app/Services/Form/FormSubmissionPdfRenderer.php' => ['form'],
        'app/Http/Controllers/Reporting/Concerns/RendersReportPdf.php' => ['report'],
        'app/Http/Controllers/DiaryCaseFileController.php' => ['case_file'],
        'app/Http/Controllers/PerDiemTripController.php' => ['report'],
        'app/Http/Controllers/CustomerPortal/DiaryDetailController.php' => ['report'],
        'app/Http/Controllers/CustomerPortal/BillingController.php' => ['report'],
        'app/Http/Controllers/Privacy/IncidentController.php' => ['report'],
        'app/Http/Controllers/Privacy/DpiaController.php' => ['report'],
        // Betroffenen-Auskunft Art. 15 (Feature 129, MVP-693).
        'app/Services/Privacy/SubjectDataExporter.php' => ['report'],
        // GoBD-Verfahrensdokumentation (Feature 134, MVP-699).
        'app/Services/Finance/ProcedureDocumentation/ProcedureDocumentationService.php' => ['report'],
        'app/Http/Controllers/Admin/PrivacyController.php' => ['report'],
        // HOAI-Stufenbericht (Feature 109, MVP-644).
        'app/Http/Controllers/Gaeb/HoaiCostReportController.php' => ['report'],
        // Rundgangsbericht (Feature 089, MVP-665-Folgepunkt).
        'app/Http/Controllers/PatrolController.php' => ['report'],
        'app/Http/Controllers/Whistleblowing/WhistleblowingPortalController.php' => ['report'],
        // Spezialformat (deklariert eingeschränkt, siehe RenderDocumentKind::capabilityNote())
        'app/Http/Controllers/LabelController.php' => ['label'],
    ];

    /**
     * Direkte `createPdfString`-Aufrufe außerhalb von `renderPdf()`:
     * Subsystem-interne Stellen und begründete Ausnahmen.
     *
     * @var array<string, string>
     */
    public const RAW_WRITER_CALLS = [
        'app/Services/DocumentDesign/DocumentDesignRenderer.php' => 'Die Pipeline selbst.',
        'app/Services/DocumentDesign/SampleDocumentService.php' => 'Editor-Test-/Vorschaudokumente des Subsystems.',
        'app/Services/Invoicing/InvoicePdfRenderer.php' => 'Manuelle compose()-Pipeline: dasselbe HTML speist auch den ZUGFeRD-Pfad.',
        'app/Services/Ideas/IdeaMapExportService.php' => 'Begründete Ausnahme: Mindmap-Gliederungsexport ohne Geschäftsdokument-Charakter (nur Branding-Layout).',
        'app/Services/Demo/DemoSeederService.php' => 'Begründete Ausnahme: synthetischer Demo-Anhang beim Seeding.',
    ];

    /**
     * Registrierte Arten, für die noch kein Generator existiert — sie sind
     * für Design-Varianten und Vorschau bereits wählbar und erben bis dahin
     * über ihre Fallback-Art. Seit MVP-650 leer: Angebot,
     * Auftragsbestätigung und Mahnung haben eigene Generatoren.
     *
     * @var array<int, string>
     */
    public const PLANNED_KINDS = [];

    /**
     * Alle Arten, die mindestens ein Generator (oder PLANNED_KINDS) trägt —
     * Vollständigkeitsabgleich gegen {@see RenderDocumentKind::cases()}.
     *
     * @return array<int, string>
     */
    public static function coveredKindValues(): array {
        $covered = self::PLANNED_KINDS;
        foreach (self::GENERATORS as $kinds) {
            $covered = array_merge($covered, $kinds);
        }
        sort($covered);

        return array_values(array_unique($covered));
    }
}
