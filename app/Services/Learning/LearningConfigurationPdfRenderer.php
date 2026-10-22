<?php
/*
 * Created on   : Thu Oct 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LearningConfigurationPdfRenderer.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Learning;

use App\Enums\DocumentDesign\RenderDocumentKind;
use App\Enums\Learning\LearningCourseStatus;
use App\Models\Learning\{LearningCourse, LearningCourseCategory};
use App\Models\Platform\Organization;
use App\Services\DocumentDesign\DocumentDesignRenderer;
use App\Services\Retention\RetentionRegistry;
use App\Support\CarbonFmt;
use Illuminate\Support\Carbon;

/**
 * Konfigurationsausdruck der Lernplattform (Feature 149 §26, MVP-1271) —
 * Anlage zur Betriebsvereinbarung: Schalter der Organisation, Lernzeitregel
 * je Kurs und Aufbewahrung, mit Stand und Konfigurationsversion. Jede
 * Änderung der Einstellungen erhöht die Version und steht im Audit-Log.
 */
class LearningConfigurationPdfRenderer {
    public const RETENTION_AREAS = ['learning_records', 'learning_certificates'];

    public function __construct(
        private readonly LearningGamificationService $gamification,
        private readonly RetentionRegistry $retention,
    ) {}

    public function output(Organization $organization, ?Carbon $now = null): string {
        $settings = (array) ($organization->settings['learning'] ?? []);
        $yes = (string) __('learning.field.yes');
        $no = (string) __('learning.field.no');

        $rows = [
            __('learning.field.scope_to_courses') => ($settings['scope_to_courses'] ?? false) ? $yes : $no,
            __('learning.field.gamification_enabled') => $this->gamification->isEnabled($organization) ? $yes : $no,
            __('learning.field.leaderboard_enabled') => $this->gamification->isLeaderboardEnabled($organization) ? $yes : $no,
            __('learning.field.streak_enabled') => $this->gamification->isStreakEnabled($organization) ? $yes : $no,
            __('learning.field.embed_hosts') => implode(', ', array_map('strval', (array) ($settings['embed_hosts'] ?? []))) ?: '—',
            __('learning.field.categories') => LearningCourseCategory::query()->where('organization_id', $organization->id)->orderBy('position')->pluck('name')->implode(', ') ?: '—',
        ];

        $courses = LearningCourse::query()
            ->where('organization_id', $organization->id)
            ->where('status', '!=', LearningCourseStatus::Archived->value)
            ->with(['versions' => fn ($q) => $q->where('is_current', true)])
            ->orderBy('title')
            ->get();

        $retention = [];
        foreach (self::RETENTION_AREAS as $area) {
            $retention[] = [
                'label' => $this->retention->labelFor($area),
                'years' => $this->retention->yearsFor($organization, $area),
                'basis' => $this->retention->basisFor($organization, $area),
            ];
        }

        return app(DocumentDesignRenderer::class)->renderPdf(
            RenderDocumentKind::Report,
            'learning.pdf.configuration',
            [
                'organization' => $organization,
                'printedAt' => CarbonFmt::orgTz($now ?? Carbon::now()),
                'version' => (int) ($settings['version'] ?? 0),
                'rows' => $rows,
                'courses' => $courses,
                'retention' => $retention,
            ],
            $organization,
        );
    }
}
