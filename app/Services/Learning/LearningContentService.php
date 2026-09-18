<?php
/*
 * Created on   : Fri Aug 28 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LearningContentService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Learning;

use App\Enums\Learning\LearningBlockKind;
use App\Models\Learning\LearningUnit;
use App\Models\{Organization, ProcedureTemplate};
use CommonToolkit\Helper\Data\JsonHelper;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Inhaltsblöcke einer Lerneinheit (Feature 149, MVP-736) — einzige
 * Schreibstelle für `learning_units.content`.
 *
 * Drei Regeln stecken hier:
 *  1. Blöcke sind **strukturiert**, nicht freies HTML. Text bleibt Text
 *     (die SafeHtml-Grenze des Frontends gilt auch serverseitig).
 *  2. Eine Einbettung ist nur erlaubt, wenn ihr Host in der
 *     `frame-src`-Allowlist der Organisation steht — sonst würde die CSP
 *     sie im Kurs still blockieren und niemand wüsste warum.
 *  3. Ein freigegebener Kurs ist gesperrt (Guard im LearningCourseService).
 */
class LearningContentService {
    /** Feldbild je Blocktyp — was nicht hier steht, wird verworfen. */
    private const FIELDS = [
        'heading' => ['text'],
        'text' => ['text'],
        'callout' => ['text', 'tone'],
        'checklist' => ['items'],
        'image' => ['attachment_id', 'alt', 'caption'],
        'file' => ['attachment_id', 'caption'],
        'video' => ['url', 'attachment_id', 'caption', 'require_percent', 'autoplay', 'remember_position'],
        'embed' => ['url', 'caption'],
        'knowledge' => ['knowledge_article_id', 'caption'],
        // MVP-806
        'gallery' => ['images', 'caption'],
        'audio' => ['attachment_id', 'caption', 'text'],
        'code' => ['text', 'language'],
        'accordion' => ['sections'],
        'table' => ['rows', 'caption'],
        'procedure' => ['procedure_template_id', 'caption'],
        'question' => ['text', 'items', 'explanation'],
        'divider' => [],
    ];

    public const GALLERY_MAX_IMAGES = 12;

    public const ACCORDION_MAX_SECTIONS = 20;

    public const TABLE_MAX_ROWS = 50;

    public const TABLE_MAX_COLUMNS = 8;

    public const QUESTION_MAX_OPTIONS = 8;

    /**
     * Block anhängen.
     *
     * @param  array<string, mixed>  $payload
     * @return list<array<string, mixed>>
     */
    public function appendBlock(LearningUnit $unit, LearningBlockKind $kind, array $payload): array {
        $blocks = $unit->blocks();
        $blocks[] = $this->normalize($unit, $kind, $payload);

        return $this->store($unit, $blocks);
    }

    /**
     * Block an Position entfernen (0-basiert).
     *
     * @return list<array<string, mixed>>
     */
    public function removeBlock(LearningUnit $unit, int $index): array {
        $blocks = $unit->blocks();

        if (! array_key_exists($index, $blocks)) {
            throw ValidationException::withMessages([
                'block' => (string) __('learning.errors.block_missing'),
            ]);
        }

        unset($blocks[$index]);

        return $this->store($unit, array_values($blocks));
    }

    /**
     * Block um eine Position verschieben.
     *
     * @return list<array<string, mixed>>
     */
    public function moveBlock(LearningUnit $unit, int $index, int $direction): array {
        $blocks = $unit->blocks();
        $target = $index + ($direction < 0 ? -1 : 1);

        if (! array_key_exists($index, $blocks) || ! array_key_exists($target, $blocks)) {
            throw ValidationException::withMessages([
                'block' => (string) __('learning.errors.block_missing'),
            ]);
        }

        [$blocks[$index], $blocks[$target]] = [$blocks[$target], $blocks[$index]];

        return $this->store($unit, $blocks);
    }

    /**
     * Erlaubte Einbettungs-Hosts der Organisation. Vorgabe ist leer —
     * ohne ausdrückliche Freigabe wird nichts eingebettet.
     *
     * @return list<string>
     */
    public function allowedHosts(Organization $organization): array {
        $settings = $organization->settings ?? [];
        $hosts = $settings['learning']['embed_hosts'] ?? [];

        if (! is_array($hosts)) {
            return [];
        }

        return array_values(array_filter(array_map(
            static fn (mixed $host): string => is_string($host) ? mb_strtolower(trim($host)) : '',
            $hosts
        )));
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function normalize(LearningUnit $unit, LearningBlockKind $kind, array $payload): array {
        // Jeder Enum-Fall hat einen Eintrag in FIELDS — deshalb ohne Fallback.
        $allowed = self::FIELDS[$kind->value];
        $block = ['type' => $kind->value];

        foreach ($allowed as $field) {
            if (array_key_exists($field, $payload) && $payload[$field] !== null && $payload[$field] !== '') {
                $block[$field] = $payload[$field];
            }
        }

        if ($kind === LearningBlockKind::Checklist) {
            $items = $block['items'] ?? [];
            $items = is_array($items) ? $items : (preg_split('/\R/', (string) $items) ?: []);
            $block['items'] = array_values(array_filter(array_map(
                static fn (mixed $item): string => trim((string) $item),
                $items
            ), static fn (string $item): bool => $item !== ''));
        }

        // Videoschalter (MVP-788) kommen als Checkbox-Werte — gespeichert
        // wird ein echtes Bool, damit die Ansicht nicht Strings vergleicht.
        if ($kind === LearningBlockKind::Video) {
            foreach (['autoplay', 'remember_position'] as $flag) {
                if (array_key_exists($flag, $block)) {
                    $block[$flag] = filter_var($block[$flag], FILTER_VALIDATE_BOOL);
                }
            }
        }

        if ($kind->needsHostAllowlist() && isset($block['url'])) {
            $this->guardHost($unit, (string) $block['url']);
        }

        $block = match ($kind) {
            LearningBlockKind::Gallery => $this->normalizeGallery($block),
            LearningBlockKind::Audio => $this->normalizeAudio($block),
            LearningBlockKind::Accordion => $this->normalizeAccordion($block),
            LearningBlockKind::Table => $this->normalizeTable($block),
            LearningBlockKind::Procedure => $this->normalizeProcedure($unit, $block),
            LearningBlockKind::Question => $this->normalizeQuestion($block),
            default => $block,
        };

        // Der Trenner ist der einzige Block, der nichts trägt.
        if ($kind === LearningBlockKind::Divider) {
            return $block;
        }

        // Ein Block ohne Inhalt ist ein Bedienfehler, kein leerer Platzhalter.
        if (count($block) === 1) {
            throw ValidationException::withMessages([
                'block' => (string) __('learning.errors.block_empty'),
            ]);
        }

        return $block;
    }

    /**
     * Galerie: jedes Bild braucht seinen eigenen Alternativtext (WCAG 1.1.1) —
     * ein gemeinsamer Text für zwölf Bilder sagte über keines etwas.
     *
     * @param  array<string, mixed>  $block
     * @return array<string, mixed>
     */
    private function normalizeGallery(array $block): array {
        $images = [];
        foreach ((array) ($block['images'] ?? []) as $image) {
            $attachmentId = is_array($image) ? (int) ($image['attachment_id'] ?? 0) : 0;
            $alt = is_array($image) ? trim((string) ($image['alt'] ?? '')) : '';
            if ($attachmentId <= 0) {
                continue;
            }
            if ($alt === '') {
                throw ValidationException::withMessages(['alts' => (string) __('learning.errors.gallery_alt_required')]);
            }
            $images[] = ['attachment_id' => $attachmentId, 'alt' => $alt];
        }

        if (count($images) < 2 || count($images) > self::GALLERY_MAX_IMAGES) {
            throw ValidationException::withMessages(['gallery' => (string) __('learning.errors.gallery_count', ['max' => self::GALLERY_MAX_IMAGES])]);
        }

        $block['images'] = $images;

        return $block;
    }

    /**
     * Audio ohne Transkript ist für gehörlose Menschen nicht vorhanden
     * (WCAG 1.2.1) — deshalb Pflicht wie der Alternativtext beim Bild.
     *
     * @param  array<string, mixed>  $block
     * @return array<string, mixed>
     */
    private function normalizeAudio(array $block): array {
        if (trim((string) ($block['text'] ?? '')) === '') {
            throw ValidationException::withMessages(['text' => (string) __('learning.errors.audio_transcript_required')]);
        }
        if (! isset($block['attachment_id'])) {
            throw ValidationException::withMessages(['media' => (string) __('learning.errors.media_required')]);
        }

        return $block;
    }

    /**
     * Abschnitte durch Leerzeilen getrennt; erste Zeile Überschrift, der Rest
     * der Inhalt.
     *
     * @param  array<string, mixed>  $block
     * @return array<string, mixed>
     */
    private function normalizeAccordion(array $block): array {
        $raw = $block['sections'] ?? [];
        $sections = [];

        if (is_array($raw)) {
            foreach ($raw as $section) {
                $sections[] = [
                    'title' => trim((string) (is_array($section) ? ($section['title'] ?? '') : '')),
                    'body' => trim((string) (is_array($section) ? ($section['body'] ?? '') : '')),
                ];
            }
        } else {
            foreach (preg_split('/\R[ \t]*\R/', trim((string) $raw)) ?: [] as $chunk) {
                $lines = preg_split('/\R/', trim($chunk)) ?: [];
                $sections[] = [
                    'title' => trim((string) array_shift($lines)),
                    'body' => trim(implode("\n", $lines)),
                ];
            }
        }

        $sections = array_values(array_filter($sections, static fn (array $section): bool => $section['title'] !== '' || $section['body'] !== ''));
        foreach ($sections as $section) {
            if ($section['title'] === '' || $section['body'] === '') {
                throw ValidationException::withMessages(['sections' => (string) __('learning.errors.accordion_section_incomplete')]);
            }
        }
        if ($sections === [] || count($sections) > self::ACCORDION_MAX_SECTIONS) {
            throw ValidationException::withMessages(['sections' => (string) __('learning.errors.accordion_count', ['max' => self::ACCORDION_MAX_SECTIONS])]);
        }

        $block['sections'] = $sections;

        return $block;
    }

    /**
     * Zeilen mit Tabulator (aus der Tabellenkalkulation kopiert) oder `|`
     * getrennt; die erste Zeile sind die Spaltenköpfe. Markdown-Trennzeilen
     * (`---|---`) fallen weg.
     *
     * @param  array<string, mixed>  $block
     * @return array<string, mixed>
     */
    private function normalizeTable(array $block): array {
        $raw = $block['rows'] ?? [];
        $rows = [];

        foreach (is_array($raw) ? $raw : (preg_split('/\R/', (string) $raw) ?: []) as $line) {
            if (is_array($line)) {
                $cells = array_map(static fn (mixed $cell): string => trim((string) $cell), $line);
            } else {
                $line = trim((string) $line);
                if ($line === '' || preg_match('/^\|?\s*:?-{3,}:?\s*(\|\s*:?-{3,}:?\s*)*\|?$/', $line)) {
                    continue;
                }
                $cells = array_map('trim', str_contains($line, "\t") ? explode("\t", $line) : explode('|', trim($line, '|')));
            }
            $rows[] = array_values($cells);
        }

        $columns = $rows === [] ? 0 : max(array_map('count', $rows));
        if (count($rows) < 2 || count($rows) > self::TABLE_MAX_ROWS + 1 || $columns > self::TABLE_MAX_COLUMNS) {
            throw ValidationException::withMessages(['rows' => (string) __('learning.errors.table_shape', [
                'rows' => self::TABLE_MAX_ROWS,
                'columns' => self::TABLE_MAX_COLUMNS,
            ])]);
        }
        if (in_array('', $rows[0], true) || count($rows[0]) < $columns) {
            throw ValidationException::withMessages(['rows' => (string) __('learning.errors.table_header_incomplete')]);
        }

        // Kurze Zeilen auffüllen: sonst verrutschen Zellen unter falsche Köpfe.
        $block['rows'] = array_map(static fn (array $row): array => array_pad($row, $columns, ''), $rows);

        return $block;
    }

    /**
     * Prozedur aus der eigenen Organisation, und nur eine aktive — eine
     * archivierte Anleitung im Kurs lehrte einen überholten Ablauf.
     *
     * @param  array<string, mixed>  $block
     * @return array<string, mixed>
     */
    private function normalizeProcedure(LearningUnit $unit, array $block): array {
        $templateId = (int) ($block['procedure_template_id'] ?? 0);
        $exists = $templateId > 0 && ProcedureTemplate::query()->withoutGlobalScopes()
            ->whereKey($templateId)
            ->where('organization_id', $unit->organization_id)
            ->where('active', true)
            ->exists();

        if (! $exists) {
            throw ValidationException::withMessages(['procedure_template_id' => (string) __('learning.errors.procedure_invalid')]);
        }

        $block['procedure_template_id'] = $templateId;

        return $block;
    }

    /**
     * Verständnisfrage ohne Bewertung: Antwortmöglichkeiten je Zeile, richtige
     * mit `*` markiert. Ohne Antwortmöglichkeiten trägt die Erklärung die
     * Auflösung.
     *
     * @param  array<string, mixed>  $block
     * @return array<string, mixed>
     */
    private function normalizeQuestion(array $block): array {
        if (trim((string) ($block['text'] ?? '')) === '') {
            throw ValidationException::withMessages(['text' => (string) __('learning.errors.question_text_required')]);
        }

        $options = [];
        $raw = $block['items'] ?? [];
        foreach (is_array($raw) ? $raw : (preg_split('/\R/', (string) $raw) ?: []) as $line) {
            $line = trim((string) $line);
            if ($line === '') {
                continue;
            }
            $correct = str_starts_with($line, '*');
            $text = trim($correct ? substr($line, 1) : $line);
            if ($text !== '') {
                $options[] = ['text' => $text, 'correct' => $correct];
            }
        }
        unset($block['items']);

        if ($options !== []) {
            $correctCount = count(array_filter($options, static fn (array $option): bool => $option['correct']));
            if (count($options) < 2 || count($options) > self::QUESTION_MAX_OPTIONS || $correctCount === 0) {
                throw ValidationException::withMessages(['items' => (string) __('learning.errors.question_options', ['max' => self::QUESTION_MAX_OPTIONS])]);
            }
            $block['options'] = $options;
        } elseif (trim((string) ($block['explanation'] ?? '')) === '') {
            throw ValidationException::withMessages(['explanation' => (string) __('learning.errors.question_resolution_required')]);
        }

        return $block;
    }

    /**
     * Die CSP ist beidseitig aktiv — ein nicht freigegebener Host würde im
     * Kurs still blockiert. Deshalb wird er hier sichtbar abgelehnt.
     */
    private function guardHost(LearningUnit $unit, string $url): void {
        $host = mb_strtolower((string) parse_url($url, PHP_URL_HOST));
        // Nur https: `javascript://erlaubter.host/%0A…` trägt einen erlaubten Host
        // und würde im iframe als Skript laufen.
        $scheme = mb_strtolower((string) parse_url($url, PHP_URL_SCHEME));

        if ($host === '' || $scheme !== 'https') {
            throw ValidationException::withMessages([
                'url' => (string) __('learning.errors.embed_url_invalid'),
            ]);
        }

        $organization = $unit->organization;
        $allowed = $organization !== null ? $this->allowedHosts($organization) : [];

        foreach ($allowed as $candidate) {
            if ($host === $candidate || str_ends_with($host, '.' . $candidate)) {
                return;
            }
        }

        throw ValidationException::withMessages([
            'url' => (string) __('learning.errors.embed_host_not_allowed', ['host' => $host]),
        ]);
    }

    /**
     * @param  list<array<string, mixed>>  $blocks
     * @return list<array<string, mixed>>
     */
    private function store(LearningUnit $unit, array $blocks): array {
        return DB::transaction(function () use ($unit, $blocks): array {
            $unit->update([
                'content' => JsonHelper::encode($blocks, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ]);

            return $unit->refresh()->blocks();
        });
    }
}
