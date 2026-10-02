<?php
/*
 * Created on   : Fri Oct 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DictationStructureService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Ai\Suggestions;

use App\Models\Platform\Organization;
use App\Services\Ai\AiInvocationService;
use App\Services\Ai\Dto\{AiExtractionResult, ExtractRequest};
use App\Services\Ai\Exceptions\AiException;
use App\Services\Ai\Support\CustomerNameMasker;
use App\Services\Media\Contracts\DictationStructurer;

/**
 * Diktat gliedern (MVP-1060): das Transkript wird in die Felder des Formulars
 * zerlegt und in Fachsprache gebracht. Nur Vorschlag — der Mensch übernimmt.
 * Kundennamen gehen maskiert hinaus; scheitert die KI, bleibt das Transkript.
 */
final class DictationStructureService implements DictationStructurer {
    public const CAPABILITY = 'dictation.structure';

    /** Gliederung je Feld — abschließend, die KI liefert nur diese Felder. */
    private const SCHEMA = [
        'activity' => 'Was zu tun war bzw. der Anlass des Einsatzes, sachlich in Fachsprache; null, wenn nicht genannt.',
        'work_done' => 'Die erledigten Arbeiten, sachlich in Fachsprache, gern als kurze Aufzählung; null, wenn nicht genannt.',
        'defects' => 'Festgestellte Mängel, Schäden oder offene Punkte; null, wenn keine genannt wurden.',
        'note' => 'Der Inhalt als sachliche Notiz in Fachsprache.',
    ];

    public function __construct(
        private readonly AiInvocationService $invocation,
        private readonly CustomerNameMasker $masker,
    ) {}

    public function structure(Organization $organization, string $text, array $fields, string $locale): ?array {
        $schema = array_intersect_key(self::SCHEMA, array_flip($fields));
        if ($schema === []) {
            return null;
        }
        try {
            $result = $this->invocation->invoke($organization, self::CAPABILITY, new ExtractRequest(
                text: $this->masker->mask($organization, $text),
                schema: $schema,
                language: $locale,
            ))->result;
        } catch (AiException) {
            return null;
        }
        if (! $result instanceof AiExtractionResult) {
            return null;
        }
        $structured = [];
        foreach (array_keys($schema) as $field) {
            $value = $result->confidentValue($field);
            if (is_string($value) && trim($value) !== '') {
                $structured[$field] = trim($value);
            }
        }

        return $structured === [] ? null : $structured;
    }
}
