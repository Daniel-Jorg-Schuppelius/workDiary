<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LeadIntakePdfRenderer.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Sales\Intake;

use App\Enums\DocumentDesign\RenderDocumentKind;
use App\Models\Attachments\Attachment;
use App\Models\Customer\Customer;
use App\Models\Platform\User;
use App\Models\Sales\{Lead, LeadIntake};
use App\Services\Attachments\FileAttacher;
use App\Services\DocumentDesign\DocumentDesignRenderer;
use CommonToolkit\Helper\FileSystem\File;
use Illuminate\Support\{Carbon, Str};
use PDFToolkit\Helper\PDFHelper;

/**
 * PDF der Aufnahmeakte (Feature 200, MVP-1644) im Papierlayout aus Feature
 * 203: Kopftabelle, je Abschnitt „Thema / Angaben“, Auswahlen als ☐/☑, jedes
 * Prüfpaket auf eigener Seite, Fußzeile Stand · Kunde · Seite x von y (zweiter
 * Lauf mit der Seitenzahl, wie {@see \App\Services\Form\FormSubmissionPdfRenderer}).
 *
 * Eng sichtbare Angaben (Ausweis, Gesundheit) stehen nur im PDF für eine
 * Person mit dem Recht; die Ablage am Kunden bzw. Lead erbt dessen
 * Sichtbarkeit und trägt sie deshalb nie (nur „eingeschränkt“).
 */
final class LeadIntakePdfRenderer {
    public function __construct(
        private readonly DocumentDesignRenderer $design,
        private readonly LeadIntakeVisibility $visibility,
        private readonly FileAttacher $files,
    ) {}

    /** PDF für die Person (null = ohne enge Angaben, etwa für die Ablage). */
    public function output(LeadIntake $intake, ?User $viewer): string {
        $intake->loadMissing(['lead', 'customer', 'decider', 'secondReviewer']);
        $schema = $intake->fullSchema();
        $values = $this->visibility->valuesFor($intake, $viewer);
        $hidden = $this->visibility->hiddenFor($intake, $viewer);
        $render = fn (?int $pages): string => $this->design->renderPdf(
            RenderDocumentKind::Form,
            'leads.intakes.pdf',
            [
                'intake' => $intake,
                'schema' => $schema,
                'values' => $values,
                'hidden' => $hidden,
                'reference' => $this->reference($intake),
                'pages' => $pages,
            ],
            (int) $intake->organization_id,
        );

        $first = $render(null);
        $pages = File::withTemp($first, static fn (string $path): int => PDFHelper::getPageCount($path), 'intake-pdf-', 'pdf');

        return $pages > 0 ? $render($pages) : $first;
    }

    /** Dateiname aus Paket, Name und Datum: `aufnahme-gwg-kanzlei-beispiel-2026-11-12.pdf`. */
    public function filename(LeadIntake $intake): string {
        $packages = implode('-', array_map(static fn ($snapshot): string => $snapshot->key, $intake->packageSnapshots()));
        $parts = array_filter([
            (string) __('lead_intake.pdf.file_prefix'),
            $packages !== '' ? $packages : null,
            Str::slug(Str::limit($this->reference($intake), 60, '')),
            Carbon::now()->format('Y-m-d'),
        ]);

        return Str::slug(implode('-', $parts)) . '.pdf';
    }

    /**
     * Ablage als Dokument am Kunden (nach der Umwandlung) bzw. am Lead —
     * immer ohne enge Angaben.
     */
    public function archive(LeadIntake $intake, User $actor): Attachment {
        $intake->loadMissing(['lead', 'customer']);
        $carrier = $intake->customer instanceof Customer ? $intake->customer : $intake->lead;
        if (! $carrier instanceof Customer && ! $carrier instanceof Lead) {
            throw new \RuntimeException((string) __('lead_intake.error.no_carrier'));
        }
        $attachment = $this->files->storeContent($carrier, $this->output($intake, null), $this->filename($intake), 'application/pdf', (int) $actor->id, [
            'organization_id' => $intake->organization_id,
        ]);
        $intake->audit('lead_intake.pdf_archived', ['attachment_id' => $attachment->id, 'carrier' => $carrier->getMorphClass()]);

        return $attachment;
    }

    /** Bezug in Kopf und Fußzeile: Kunde, sonst Lead. */
    public function reference(LeadIntake $intake): string {
        return (string) ($intake->customer?->name ?: $intake->lead?->displayName() ?: '—');
    }
}
