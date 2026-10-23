<?php
/*
 * Created on   : Fri Oct 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ManagementReportPdf.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Reporting;

use App\Enums\DocumentDesign\RenderDocumentKind;
use App\Enums\User\Permission;
use App\Models\Platform\{Organization, User};
use App\Services\DocumentDesign\DocumentDesignRenderer;
use App\Services\Reporting\Dto\EarlyWarning;
use Carbon\CarbonImmutable;

/**
 * Leitungsbericht als PDF (Feature 184) — für den Download der Leitungssicht
 * und den geplanten Versand. Inhalt je Betrachter: nur Kennzahlen mit seinem
 * Recht, Frühwarnungen nur mit dem Recht der Leitungssicht.
 */
final class ManagementReportPdf {
    public const VIEW = 'reports.pdf.management';

    public function __construct(
        private readonly ManagementOverviewService $overview,
        private readonly EarlyWarningService $warnings,
        private readonly DocumentDesignRenderer $renderer,
    ) {}

    /** @return array{kpis: array<string, list<Dto\ManagementKpi>>, warnings: list<EarlyWarning>, from: CarbonImmutable, to: CarbonImmutable, organization: Organization} */
    public function data(User $viewer, Organization $organization, CarbonImmutable $from, CarbonImmutable $to): array {
        $seesWarnings = $viewer->isAdmin() || $viewer->can(Permission::ReportView->value);

        return [
            'kpis' => $this->overview->build($viewer, $organization, $from, $to),
            'warnings' => $seesWarnings ? $this->warnings->collect($organization) : [],
            'from' => $from,
            'to' => $to,
            'organization' => $organization,
        ];
    }

    public function render(User $viewer, Organization $organization, CarbonImmutable $from, CarbonImmutable $to): string {
        return $this->renderer->renderPdf(RenderDocumentKind::Report, self::VIEW, $this->data($viewer, $organization, $from, $to), $organization);
    }
}
