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
use App\Models\Organization;
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
        'video' => ['url', 'attachment_id', 'caption', 'require_percent'],
        'embed' => ['url', 'caption'],
        'knowledge' => ['knowledge_article_id', 'caption'],
    ];

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

        if ($kind->needsHostAllowlist() && isset($block['url'])) {
            $this->guardHost($unit, (string) $block['url']);
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
     * Die CSP ist beidseitig aktiv — ein nicht freigegebener Host würde im
     * Kurs still blockiert. Deshalb wird er hier sichtbar abgelehnt.
     */
    private function guardHost(LearningUnit $unit, string $url): void {
        $host = mb_strtolower((string) parse_url($url, PHP_URL_HOST));

        if ($host === '') {
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
                'content' => json_encode($blocks, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ]);

            return $unit->refresh()->blocks();
        });
    }
}
