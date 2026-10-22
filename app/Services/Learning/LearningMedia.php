<?php
/*
 * Created on   : Wed Oct 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LearningMedia.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Learning;

use App\Models\Learning\LearningEnrollment;
use App\Models\Media\MediaRendition;
use App\Models\Platform\User;
use App\Services\Media\MediaPresenter;

/**
 * Videozustand je Anhang der Kurseinheiten — für Mitarbeitende und
 * Kundenportal gleich, nur die Route der Videofassung unterscheidet sich
 * (Konsolidierungs-Audit 2026-10-21, ko-16).
 */
final class LearningMedia {
    /** Benutzereinstellung der gewählten Videoqualität (MVP-1022). */
    public const VIDEO_QUALITY_PREFERENCE = 'video_quality';

    public function __construct(private readonly MediaPresenter $presenter) {}

    /** @return array<int, array<string, mixed>> */
    public function stateFor(LearningEnrollment $enrollment, string $renditionRoute, User $viewer): array {
        $attachments = [];
        $unitOf = [];
        foreach ($enrollment->course->units ?? [] as $unit) {
            foreach ($unit->attachments as $attachment) {
                $attachments[] = $attachment;
                $unitOf[(int) $attachment->id] = $unit;
            }
        }

        return $this->presenter->forAttachments(
            $attachments,
            static fn (MediaRendition $rendition): string => route($renditionRoute, [
                'enrollment' => $enrollment->sqid,
                'unit' => $unitOf[(int) $rendition->attachment_id]->sqid,
                'rendition' => $rendition->sqid,
            ]),
            $viewer->getPreference(self::VIDEO_QUALITY_PREFERENCE),
        );
    }
}
