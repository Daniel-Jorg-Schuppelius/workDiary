<?php
/*
 * Created on   : Tue Aug 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CustomerNameMasker.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Ai\Support;

use App\Models\{Customer, Organization};

/**
 * Datenschutz-Vorfilter aller KI-Prompts (Feature 084, Vollaudit 2026-07
 * M35; seit MVP-711 geteilt): maskiert Namen aus dem eigenen Kundenstamm im
 * Prompttext, BEVOR der Text einen (ggf. Cloud-)Provider erreicht. Namen
 * unter 4 Zeichen bleiben unberührt (False-Positive-Schutz); Obergrenze
 * 5000 Namen je Organisation.
 */
final class CustomerNameMasker {
    public const PLACEHOLDER = '[Kunde]';

    public function mask(Organization $organization, string $text): string {
        if (trim($text) === '') {
            return $text;
        }

        $names = Customer::query()
            ->withoutGlobalScopes()
            ->where('organization_id', $organization->id)
            ->orderBy('id')
            ->limit(5000)
            ->pluck('name')
            ->map(static fn($n): string => trim((string) $n))
            ->filter(static fn(string $n): bool => mb_strlen($n) >= 4)
            ->sortByDesc(static fn(string $n): int => mb_strlen($n))
            ->values();

        foreach ($names->chunk(200) as $chunk) {
            $pattern = '/(?:' . $chunk->map(static fn(string $n): string => preg_quote($n, '/'))->implode('|') . ')/iu';
            $text = preg_replace($pattern, self::PLACEHOLDER, $text) ?? $text;
        }

        return $text;
    }
}
