<?php
/*
 * Created on   : Mon Aug 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TextCorrectionService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Invoicing;

use App\Models\Scopes\OrganizationScope;
use App\Models\TextCorrection;
use CommonToolkit\Helper\Data\StringHelper;

/**
 * Wendet das Schreibfehler-Wörterbuch der Organisation deterministisch auf
 * generierte Positionstexte an (Whole-Word, Case-Erhalt — {@see StringHelper::replaceWords()}).
 * Die Map wird je Organisation einmal pro Request/Job geladen; der Service ist
 * scoped gebunden ({@see \App\Providers\AppServiceProvider}), damit der Cache
 * nicht über Worker-Grenzen leckt. Org-ID kommt explizit vom Aufrufer, weil
 * Builder/Generator auch in Queue-/Konsolenkontexten ohne
 * currentOrganization-Bindung laufen.
 */
class TextCorrectionService {
    /** @var array<int, array<string, string>> */
    private array $maps = [];

    public function apply(?string $text, int $organizationId): ?string {
        if ($text === null || $text === '') {
            return $text;
        }

        $map = $this->mapFor($organizationId);

        return $map === [] ? $text : StringHelper::replaceWords($text, $map);
    }

    /** @return array<string, string> wrong_normalized => correct */
    private function mapFor(int $organizationId): array {
        return $this->maps[$organizationId] ??= TextCorrection::query()
            ->withoutGlobalScope(OrganizationScope::class)
            ->where('organization_id', $organizationId)
            ->where('active', true)
            ->pluck('correct', 'wrong_normalized')
            ->all();
    }
}
