<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : KnowledgePageContent.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Knowledge\Pages;

use App\Models\Attachments\Attachment;
use App\Models\Knowledge\{KnowledgeGuideStep, KnowledgePage, KnowledgePageVersion};
use App\Models\Platform\User;
use App\Services\Knowledge\Pages\Guide\GuideImageAnnotator;
use CommonToolkit\Helper\Data\{DataUrlHelper, StringHelper};
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\{Storage, URL};

/**
 * Was eine Person von einer Wissensseite zu sehen bekommt (Feature 191):
 * Bearbeitende die aktuelle Fassung (Text und Schritte live), alle anderen
 * die freigegebene Fassung aus der Historie. Dazu das HTML des Textes mit
 * Bildern aus den Anhängen — auf dem Bildschirm als signierte Links, im PDF
 * als eingebettete Daten.
 *
 * @phpstan-type PageStep array{body: string|null, image: Attachment|null, markers: list<array<string, mixed>>}
 * @phpstan-type PageContent array{version: int|null, title: string, body: string, steps: list<PageStep>, approved_at: Carbon|null, approver: string|null, current: bool}
 */
final class KnowledgePageContent {
    /** Größere Bilder bettet das PDF nicht ein. */
    private const int MAX_PDF_IMAGE_BYTES = 6_000_000;

    public function __construct(
        private readonly KnowledgePageAccess $access,
        private readonly KnowledgeMarkdownRenderer $renderer,
        private readonly GuideImageAnnotator $annotator,
    ) {}

    /**
     * @param  int|null  $version  ältere Fassung (nur für Bearbeitende)
     * @return PageContent
     */
    public function forViewer(KnowledgePage $page, User $viewer, ?int $version = null): array {
        $editor = $this->access->canEdit($viewer);
        if ($editor && ($version === null || $version === $page->version)) {
            return [
                'version' => $page->version,
                'title' => $page->title,
                'body' => (string) $page->body,
                'steps' => $page->isGuide() ? $this->liveSteps($page) : [],
                'approved_at' => $page->approved_version === $page->version ? $page->approved_at : null,
                'approver' => $page->approved_version === $page->version ? $page->approver?->name : null,
                'current' => true,
            ];
        }

        return $this->fromVersion($page, $editor ? $version : $page->approved_version);
    }

    /**
     * Die freigegebene Fassung (Notfallhandbuch, PDF für Leser).
     *
     * @return PageContent
     */
    public function approved(KnowledgePage $page): array {
        return $this->fromVersion($page, $page->approved_version);
    }

    /** HTML des Seitentextes mit Bildern aus den Anhängen. */
    public function html(KnowledgePage $page, string $markdown, bool $forPdf = false): string {
        return $this->renderer->render($markdown, $this->images($page, $forPdf));
    }

    /**
     * Bildadresse eines Schritts; im PDF mit eingezeichneten Markierungen.
     *
     * @param  list<array<string, mixed>>  $markers
     */
    public function stepImage(?Attachment $image, array $markers, bool $forPdf): ?string {
        if (! $image instanceof Attachment) {
            return null;
        }
        if (! $forPdf) {
            return $this->signedUrl($image);
        }
        $bytes = $this->bytes($image);
        if ($bytes === null) {
            return null;
        }
        $burned = $this->annotator->burn($bytes, $markers);
        $encoded = $burned !== null ? DataUrlHelper::encode($burned, 'image/png') : DataUrlHelper::encode($bytes, (string) $image->mime);

        return is_string($encoded) ? $encoded : null;
    }

    /** @return PageContent */
    private function fromVersion(KnowledgePage $page, ?int $number): array {
        $row = $number !== null
            ? KnowledgePageVersion::query()->with('approver:id,name')->where('knowledge_page_id', $page->id)->where('version', $number)->first()
            : null;
        if (! $row instanceof KnowledgePageVersion) {
            return ['version' => null, 'title' => $page->title, 'body' => '', 'steps' => [], 'approved_at' => null, 'approver' => null, 'current' => false];
        }

        return [
            'version' => $row->version,
            'title' => $row->title,
            'body' => (string) $row->body,
            'steps' => $page->isGuide() ? $this->snapshotSteps($page, $row->steps ?? []) : [],
            'approved_at' => $row->approved_at,
            'approver' => $row->approver?->name,
            'current' => $row->version === $page->version,
        ];
    }

    /** @return list<PageStep> */
    private function liveSteps(KnowledgePage $page): array {
        return array_values($page->steps()->with('image')->get()->map(static fn (KnowledgeGuideStep $step): array => [
            'body' => $step->body,
            'image' => $step->image,
            'markers' => $step->markers ?? [],
        ])->all());
    }

    /**
     * @param  list<array<string, mixed>>  $steps
     * @return list<PageStep>
     */
    private function snapshotSteps(KnowledgePage $page, array $steps): array {
        $ids = array_values(array_filter(array_map(static fn (array $step): int => (int) ($step['attachment_id'] ?? 0), $steps)));
        $images = $ids === [] ? collect() : Attachment::query()
            ->where('attachable_type', $page->getMorphClass())
            ->where('attachable_id', $page->id)
            ->whereKey($ids)
            ->get()
            ->keyBy('id');

        return array_map(static fn (array $step): array => [
            'body' => isset($step['body']) ? (string) $step['body'] : null,
            'image' => $images->get((int) ($step['attachment_id'] ?? 0)),
            'markers' => array_values(is_array($step['markers'] ?? null) ? $step['markers'] : []),
        ], $steps);
    }

    /**
     * Anhänge der Seite (ohne Schrittbilder) nach Dateiname.
     *
     * @return array<string, string>
     */
    private function images(KnowledgePage $page, bool $forPdf): array {
        $map = [];
        foreach ($page->attachments()->whereNull('meta_type')->get() as $attachment) {
            if (! $attachment->isImage()) {
                continue;
            }
            $key = StringHelper::toLower(trim((string) $attachment->original_name));
            if ($key === '' || isset($map[$key])) {
                continue;
            }
            if (! $forPdf) {
                $map[$key] = $this->signedUrl($attachment);

                continue;
            }
            $bytes = $this->bytes($attachment);
            $encoded = $bytes !== null ? DataUrlHelper::encode($bytes, (string) $attachment->mime) : false;
            if (is_string($encoded)) {
                $map[$key] = $encoded;
            }
        }

        return $map;
    }

    /** Signierter Bild-Link, eine Stunde gültig — so lange bleibt eine Seite zum Lesen offen. */
    private function signedUrl(Attachment $attachment): string {
        return URL::temporarySignedRoute('attachments.download', now()->addHour(), ['attachment' => $attachment]);
    }

    private function bytes(Attachment $attachment): ?string {
        if ((int) $attachment->size > self::MAX_PDF_IMAGE_BYTES) {
            return null;
        }
        $disk = Storage::disk($attachment->disk);
        if (! $disk->exists($attachment->path)) {
            return null;
        }
        $bytes = $disk->get($attachment->path);

        return is_string($bytes) ? $bytes : null;
    }
}
