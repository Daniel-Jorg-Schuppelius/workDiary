<?php
/*
 * Created on   : Sun Aug 30 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MediaPresenter.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Media;

use App\Enums\Media\{MediaRenditionKind, MediaState};
use App\Models\Attachment;
use App\Models\Media\MediaRendition;
use Closure;
use Illuminate\Support\Collection;

/**
 * Bereitet Videozustand und Ableitungen für die Anzeige auf (Feature 150).
 *
 * **Warum ein eigener Aufbereiter:** die Blade-Vorlage soll nicht wissen,
 * wie eine Ableitung ausgewählt wird. Die Auswahlregel ist eine
 * Entscheidung, keine Darstellung — und sie gilt überall gleich.
 */
class MediaPresenter {
    /**
     * Zustand je Anhang.
     *
     * @param  Collection<int, Attachment>|array<int, Attachment>  $attachments
     * @param  Closure(MediaRendition): string  $urlFor
     * @return array<int, array<string, mixed>>
     */
    public function forAttachments(iterable $attachments, Closure $urlFor): array {
        $ids = [];

        foreach ($attachments as $attachment) {
            if ($attachment->media_state !== null) {
                $ids[] = (int) $attachment->id;
            }
        }

        if ($ids === []) {
            return [];
        }

        $renditions = MediaRendition::query()
            ->whereIn('attachment_id', $ids)
            ->get()
            ->groupBy('attachment_id');

        $out = [];

        foreach ($attachments as $attachment) {
            if ($attachment->media_state === null) {
                continue;
            }

            $own = $renditions->get($attachment->id, collect());

            $out[(int) $attachment->id] = [
                'ready' => $attachment->media_state->isPlayable(),
                'failed' => $attachment->media_state === MediaState::Failed,
                'tone' => $attachment->media_state->tone(),
                'error' => $attachment->media_error,
                'duration' => $attachment->media_duration_seconds,
                'poster' => $this->urlOf($own, MediaRenditionKind::Poster, $urlFor),
                'video' => $this->bestVideoUrl($own, $urlFor),
                'subtitles' => $own
                    ->where('kind', MediaRenditionKind::Subtitle)
                    ->map(static fn (MediaRendition $r): array => [
                        'locale' => (string) $r->locale,
                        'url' => $urlFor($r),
                        // Eine ungeprüfte Maschinenspur wird ausgespielt — aber
                        // beschriftet. Wer sie einschaltet, soll wissen, dass
                        // Namen und Zahlen darin falsch sein können.
                        'machine' => $r->awaitsReview(),
                    ])
                    ->values()
                    ->all(),
            ];
        }

        return $out;
    }

    /**
     * Die **kleinste** verfügbare Fassung als Vorgabe.
     *
     * Auf einer Baustelle über Mobilfunk ist eine 480p-Datei, die läuft,
     * mehr wert als eine 1080p-Datei, die puffert. Wer mehr will, kann
     * später über eine Qualitätsauswahl hochschalten.
     *
     * @param  Collection<int, MediaRendition>  $renditions
     * @param  Closure(MediaRendition): string  $urlFor
     */
    private function bestVideoUrl(Collection $renditions, Closure $urlFor): ?string {
        $order = array_keys(VideoTranscodingService::VARIANTS);

        $videos = $renditions->where('kind', MediaRenditionKind::Video);

        foreach ($order as $variant) {
            $match = $videos->firstWhere('variant', $variant);

            if ($match instanceof MediaRendition) {
                return $urlFor($match);
            }
        }

        return null;
    }

    /**
     * @param  Collection<int, MediaRendition>  $renditions
     * @param  Closure(MediaRendition): string  $urlFor
     */
    private function urlOf(Collection $renditions, MediaRenditionKind $kind, Closure $urlFor): ?string {
        $match = $renditions->firstWhere('kind', $kind);

        return $match instanceof MediaRendition ? $urlFor($match) : null;
    }
}
