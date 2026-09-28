<?php
/*
 * Created on   : Mon Sep 28 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ProcedureDocumentationComparison.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Finance\ProcedureDocumentation;

use App\Models\Finance\ProcedureDocumentation;
use App\Models\Platform\Organization;

/**
 * Abschnittsvergleich zweier Fassungen der Verfahrensdokumentation (MVP-995):
 * Teil A je Freitext, Teil B je generiertem Abschnitt (veröffentlicht: Snapshot,
 * Entwurf: Live-Vorschau). Verglichen wird der Abschnitt als Ganzes; geänderte
 * Abschnitte stehen nebeneinander.
 */
final class ProcedureDocumentationComparison {
    public function __construct(private readonly ProcedureDocumentationService $documents) {}

    /**
     * @return list<array{part: string, key: string, title: string, status: 'unchanged'|'changed'|'added'|'removed', old: string|null, new: string|null}>
     */
    public function compare(ProcedureDocumentation $old, ProcedureDocumentation $new): array {
        $rows = [];
        foreach (ProcedureDocumentation::TEXT_FIELDS as $field) {
            $rows[] = $this->row('operator', $field, (string) __('procedure-documentation.text.' . $field), $this->text($old->{$field}), $this->text($new->{$field}));
        }

        $before = $this->sections($old);
        $after = $this->sections($new);
        foreach (array_unique([...array_keys($before), ...array_keys($after)]) as $key) {
            $title = $after[$key]['title'] ?? $before[$key]['title'] ?? $key;
            $rows[] = $this->row('generated', $key, $title, $before[$key]['text'] ?? null, $after[$key]['text'] ?? null);
        }

        return $rows;
    }

    /** @return array{part: string, key: string, title: string, status: 'unchanged'|'changed'|'added'|'removed', old: string|null, new: string|null} */
    private function row(string $part, string $key, string $title, ?string $old, ?string $new): array {
        $status = match (true) {
            $old === null && $new !== null => 'added',
            $old !== null && $new === null => 'removed',
            $old === $new => 'unchanged',
            default => 'changed',
        };

        return ['part' => $part, 'key' => $key, 'title' => $title, 'status' => $status, 'old' => $old, 'new' => $new];
    }

    private function text(?string $value): ?string {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    /**
     * Abschnitte als Klartext je Schlüssel; der Erzeugungszeitpunkt zählt nicht.
     *
     * @return array<string, array{title: string, text: string}>
     */
    private function sections(ProcedureDocumentation $document): array {
        $payload = $document->isPublished()
            ? (array) $document->snapshot
            : $this->documents->preview(Organization::query()->withoutGlobalScopes()->findOrFail($document->organization_id));

        $sections = [];
        foreach ((array) ($payload['sections'] ?? []) as $section) {
            if (! is_array($section) || ! isset($section['key'])) {
                continue;
            }
            $lines = [];
            foreach ((array) ($section['fields'] ?? []) as $field) {
                $lines[] = ($field['label'] ?? '') . ': ' . ($field['value'] ?? '');
            }
            foreach ((array) ($section['tables'] ?? []) as $table) {
                $lines[] = '';
                $lines[] = (string) ($table['title'] ?? '');
                $lines[] = implode(' | ', (array) ($table['columns'] ?? []));
                foreach ((array) ($table['rows'] ?? []) as $cells) {
                    $lines[] = implode(' | ', array_map('strval', (array) $cells));
                }
            }
            foreach ((array) ($section['notes'] ?? []) as $note) {
                $lines[] = '• ' . (is_string($note) ? $note : '');
            }
            $sections[(string) $section['key']] = ['title' => (string) ($section['title'] ?? $section['key']), 'text' => trim(implode("\n", $lines))];
        }

        return $sections;
    }
}
