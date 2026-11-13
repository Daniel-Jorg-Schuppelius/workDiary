<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : KnowledgeGuideService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Knowledge\Pages\Guide;

use App\Enums\Knowledge\KnowledgeGuideMarkerKind;
use App\Models\Attachments\Attachment;
use App\Models\Knowledge\{KnowledgeGuideStep, KnowledgePage};
use App\Models\Platform\User;
use App\Services\Attachments\FileAttacher;
use App\Services\Knowledge\Pages\KnowledgePageService;
use Carbon\CarbonImmutable;
use CommonToolkit\Helper\Data\StringHelper;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\{DB, Storage};
use Illuminate\Validation\ValidationException;

/**
 * Schritt-für-Schritt-Anleitung aus Bildschirmfotos (Feature 191, MVP-1668).
 *
 * Bilder hängen als Anhänge an der Seite (`meta_type` = Anleitungsschritt),
 * damit ältere Fassungen sie weiter zeigen. Schwärzen schreibt das Bild neu
 * und verwirft das ungeschwärzte Original — auch ältere Fassungen zeigen
 * danach nur noch die geschwärzte Datei; sonst bliebe abrufbar, was weg
 * sollte.
 */
final class KnowledgeGuideService {
    public const int MAX_LABEL_LENGTH = 120;

    public function __construct(
        private readonly KnowledgePageService $pages,
        private readonly FileAttacher $files,
        private readonly GuideImageRedactor $redactor,
    ) {}

    public function addStep(KnowledgePage $page, User $actor, ?UploadedFile $image, ?string $body): KnowledgeGuideStep {
        if ($page->steps()->count() >= KnowledgeGuideStep::MAX_STEPS) {
            throw ValidationException::withMessages(['image' => (string) __('knowledge_pages.guide.error.too_many', ['max' => KnowledgeGuideStep::MAX_STEPS])]);
        }
        $step = DB::transaction(function () use ($page, $actor, $image, $body): KnowledgeGuideStep {
            $attachment = $image !== null
                ? $this->files->store($page, $image, $actor->id, ['meta_type' => KnowledgePage::META_GUIDE_STEP, 'organization_id' => $page->organization_id], 'knowledge-guides')
                : null;

            return KnowledgeGuideStep::query()->create([
                'organization_id' => $page->organization_id,
                'knowledge_page_id' => $page->id,
                'position' => (int) $page->steps()->max('position') + 1,
                'body' => $this->text($body),
                'attachment_id' => $attachment?->id,
                'markers' => [],
                'created_by' => $actor->id,
                'updated_by' => $actor->id,
            ]);
        });
        $this->pages->guideChanged($page, $actor);

        return $step;
    }

    /**
     * Text, Markierungen und Schwärzungen eines Schritts speichern. Schwärzen
     * verändert das Bild selbst und lässt sich nicht zurücknehmen.
     *
     * @param  list<array<string, mixed>>  $markers
     * @param  list<array<string, mixed>>  $redactions
     */
    public function updateStep(KnowledgeGuideStep $step, User $actor, ?string $body, array $markers, array $redactions, ?UploadedFile $replacement): KnowledgeGuideStep {
        $page = $step->page;
        if (! $page instanceof KnowledgePage) {
            abort(404);
        }
        $rects = $this->rects($redactions);
        DB::transaction(function () use ($step, $page, $actor, $body, $markers, $rects, $replacement): void {
            $changes = [
                'body' => $this->text($body),
                'markers' => $this->markers($markers),
                'updated_by' => $actor->id,
            ];
            if ($replacement !== null) {
                $attachment = $this->files->store($page, $replacement, $actor->id, ['meta_type' => KnowledgePage::META_GUIDE_STEP, 'organization_id' => $page->organization_id], 'knowledge-guides');
                $changes['attachment_id'] = $attachment->id;
                $changes['markers'] = [];
                $changes['redacted_at'] = null;
            } elseif ($rects !== []) {
                $image = $step->image;
                if (! $image instanceof Attachment) {
                    throw ValidationException::withMessages(['redactions' => (string) __('knowledge_pages.guide.error.no_image')]);
                }
                $this->redact($image, $rects);
                $changes['redacted_at'] = CarbonImmutable::now();
                $page->audit('knowledge_page.step_redacted', ['step' => $step->id, 'areas' => count($rects)]);
            }
            $step->forceFill($changes)->save();
        });
        $this->pages->guideChanged($page, $actor);

        return $step;
    }

    public function removeStep(KnowledgeGuideStep $step, User $actor): void {
        $page = $step->page;
        $step->delete();
        if ($page instanceof KnowledgePage) {
            $this->renumber($page);
            $this->pages->guideChanged($page, $actor);
        }
    }

    /** Einen Schritt nach oben (-1) oder unten (+1) schieben. */
    public function moveStep(KnowledgeGuideStep $step, User $actor, int $direction): void {
        $page = $step->page;
        if (! $page instanceof KnowledgePage) {
            return;
        }
        $steps = $page->steps()->get()->values();
        $index = $steps->search(static fn (KnowledgeGuideStep $s): bool => (int) $s->id === (int) $step->id);
        $target = is_int($index) ? $index + ($direction < 0 ? -1 : 1) : -1;
        if (! is_int($index) || $target < 0 || $target >= $steps->count()) {
            return;
        }
        DB::transaction(static function () use ($steps, $index, $target): void {
            $ordered = $steps->all();
            [$ordered[$index], $ordered[$target]] = [$ordered[$target], $ordered[$index]];
            foreach (array_values($ordered) as $position => $item) {
                $item->forceFill(['position' => $position + 1])->save();
            }
        });
        $this->pages->guideChanged($page, $actor);
    }

    /**
     * Bild schwärzen und das Original verwerfen.
     *
     * @param  list<array{x: float, y: float, w: float, h: float}>  $rects
     */
    private function redact(Attachment $image, array $rects): void {
        if (! $this->redactor->supports($image->mime)) {
            throw ValidationException::withMessages(['redactions' => (string) __('knowledge_pages.guide.error.unsupported')]);
        }
        $disk = Storage::disk($image->disk);
        $bytes = $disk->get($image->path);
        $redacted = is_string($bytes) ? $this->redactor->redact($bytes, (string) $image->mime, $rects) : null;
        if ($redacted === null) {
            throw ValidationException::withMessages(['redactions' => (string) __('knowledge_pages.guide.error.redact_failed')]);
        }
        // Überschreiben statt daneben ablegen: das ungeschwärzte Original bleibt nirgends liegen.
        $disk->put($image->path, $redacted);
        $image->forceFill(['size' => strlen($redacted)])->save();
    }

    private function renumber(KnowledgePage $page): void {
        foreach ($page->steps()->get()->values() as $position => $step) {
            if ($step->position !== $position + 1) {
                $step->forceFill(['position' => $position + 1])->save();
            }
        }
    }

    /**
     * Markierungen auf bekannte Arten und Koordinaten 0–1 bringen.
     *
     * @param  list<array<string, mixed>>  $markers
     * @return list<array<string, float|string>>
     */
    private function markers(array $markers): array {
        $clean = [];
        foreach (array_slice($markers, 0, KnowledgeGuideStep::MAX_MARKERS) as $marker) {
            $kind = KnowledgeGuideMarkerKind::tryFrom((string) ($marker['kind'] ?? ''));
            if ($kind === null) {
                continue;
            }
            $row = ['kind' => $kind->value, 'x' => $this->unit($marker['x'] ?? 0), 'y' => $this->unit($marker['y'] ?? 0)];
            if ($kind->isArea()) {
                $row['w'] = $this->unit($marker['w'] ?? 0);
                $row['h'] = $this->unit($marker['h'] ?? 0);
                if ($row['w'] <= 0.0 || $row['h'] <= 0.0) {
                    continue;
                }
            }
            if ($kind === KnowledgeGuideMarkerKind::Arrow) {
                $row['x2'] = $this->unit($marker['x2'] ?? 0);
                $row['y2'] = $this->unit($marker['y2'] ?? 0);
            }
            if (in_array($kind, [KnowledgeGuideMarkerKind::Number, KnowledgeGuideMarkerKind::Text], true)) {
                $label = StringHelper::truncate(StringHelper::normalizeWhitespace((string) ($marker['label'] ?? '')), self::MAX_LABEL_LENGTH, '');
                if ($label === '') {
                    continue;
                }
                $row['label'] = $label;
            }
            $clean[] = $row;
        }

        return $clean;
    }

    /**
     * @param  list<array<string, mixed>>  $redactions
     * @return list<array{x: float, y: float, w: float, h: float}>
     */
    private function rects(array $redactions): array {
        $rects = [];
        foreach (array_slice($redactions, 0, KnowledgeGuideStep::MAX_MARKERS) as $rect) {
            $row = ['x' => $this->unit($rect['x'] ?? 0), 'y' => $this->unit($rect['y'] ?? 0), 'w' => $this->unit($rect['w'] ?? 0), 'h' => $this->unit($rect['h'] ?? 0)];
            if ($row['w'] > 0.0 && $row['h'] > 0.0) {
                $rects[] = $row;
            }
        }

        return $rects;
    }

    private function unit(mixed $value): float {
        return round(max(0.0, min(1.0, is_numeric($value) ? (float) $value : 0.0)), 4);
    }

    private function text(?string $body): ?string {
        $text = trim((string) $body);

        return $text !== '' ? StringHelper::truncate($text, 5000, '') : null;
    }
}
