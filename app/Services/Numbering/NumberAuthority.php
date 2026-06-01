<?php
/*
 * Created on   : Mon Jun 01 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : NumberAuthority.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Services\Numbering;

use App\Enums\Numbering\NumberScope;
use App\Models\{NumberFormat, Organization};

/**
 * Entscheidet, wer die Hoheit über einen Nummernkreis hat:
 *  - 'local'    : workDiary vergibt die Nummer selbst
 *  - 'lexoffice': ein externes Buchhaltungssystem ist führend; lokal wird nur
 *                 eine Entwurfsnummer vergeben, die beim Push/Sync durch die
 *                 offizielle externe Nummer überschrieben wird.
 *
 * Die Entscheidung wird bewusst rein aus `number_formats.source` abgeleitet,
 * damit der Core entkoppelt vom Lexoffice-Plugin bleibt. Das Plugin (bzw. die
 * Admin-UI) setzt `source = 'external'` für die buchhaltungsrelevanten Scopes,
 * sobald Lexoffice die Hoheit übernehmen soll.
 */
class NumberAuthority {
    public const SOURCE_LOCAL = 'local';

    public const SOURCE_EXTERNAL = 'external';

    public function isExternal(Organization|int|null $organization, NumberScope $scope): bool {
        if (! $scope->isAccountingRelevant()) {
            return false;
        }

        $orgId = $this->orgId($organization);
        if ($orgId === null) {
            return false;
        }

        /** @var NumberFormat|null $format */
        $format = NumberFormat::query()
            ->where('organization_id', $orgId)
            ->where('scope', $scope->value)
            ->first();

        return $format !== null
            && (string) $format->getAttribute('source') === self::SOURCE_EXTERNAL;
    }

    private function orgId(Organization|int|null $organization): ?int {
        if ($organization instanceof Organization) {
            return (int) $organization->id;
        }

        return $organization === null ? null : (int) $organization;
    }
}
