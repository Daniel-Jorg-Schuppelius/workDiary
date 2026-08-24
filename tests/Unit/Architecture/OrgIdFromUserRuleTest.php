<?php
/*
 * Created on   : Sun Aug 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : OrgIdFromUserRuleTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Unit\Architecture;

use PHPUnit\Framework\TestCase;
use Tests\Unit\Architecture\Concerns\ScansSourceTree;

/**
 * Architektur-Gate für die Org-Attribution neuer Datensätze (Vollscan
 * 2026-08-23, E6): Ein Plattform-Admin arbeitet per Session-Override in einer
 * fremden Organisation (SetOrganizationContext) — `currentOrganization` ist
 * dann Org B, `users.organization_id` bleibt Org A. Schreibpfade, die die
 * organization_id aus dem User ziehen, legen den Datensatz in der falschen
 * Org an, während die Validierung (ExistsInCurrentOrganization) gegen die
 * richtige prüft: Mandat in A zeigt auf Kunden aus B.
 *
 * Regel: In app/Http wird `'organization_id' => …` nie aus
 * `$user->organization_id` / `$request->user()?->organization_id` /
 * `Auth::user()?->organization_id` befüllt, sondern aus
 * ResolvesCurrentOrganization::currentOrganization()->id (oder dem Parent-
 * Datensatz). Fachlich begründete Ausnahmen stehen in der ALLOW_LIST
 * (Welle 3, E6: alle Altfälle sind umgestellt).
 */
class OrgIdFromUserRuleTest extends TestCase {
    use ScansSourceTree;

    /** @var array<string, string> Pfad → Begründung / Nachzieh-Welle */
    private const ALLOW_LIST = [
        // Fachlich korrekt: Kontaktdaten gehören zur Org des BEARBEITETEN Users.
        'app/Http/Controllers/Concerns/ManagesUserContactDetails.php' => 'Adresse/Bank des bearbeiteten Users — dessen Org ist die richtige.',
        // Portal-Gast: eigener Guard ohne Admin-Override, Org des Portal-Users ist die richtige.
        'app/Http/Controllers/CustomerPortal/QueryController.php' => 'Portal-User ohne Session-Override.',
    ];

    public function test_http_layer_takes_organization_id_from_the_bound_organization(): void {
        $violations = [];

        foreach ($this->phpFiles('app/Http') as $file) {
            $relative = $this->relativePath($file);
            if ($this->isAllowListed($relative, self::ALLOW_LIST)) {
                continue;
            }

            $source = $this->stripComments((string) file_get_contents($file));

            if (preg_match_all('/[\'"]organization_id[\'"]\s*=>\s*(?:\(int\)\s*)?(?:\$request->user\(\)\??->|\$user->|\$actor->|\$admin->|\$this->user\(\)\??->|Auth::user\(\)\??->|auth\(\)->user\(\)\??->)organization_id/', $source, $matches, PREG_OFFSET_CAPTURE) === 0) {
                continue;
            }

            foreach ($matches[0] as [$match, $offset]) {
                $violations[] = sprintf('%s:%d — %s', $relative, $this->lineOf($source, (int) $offset), $match);
            }
        }

        sort($violations);

        $this->assertSame([], $violations, "organization_id aus dem User statt aus der gebundenen Organisation.\n"
            . "ResolvesCurrentOrganization::currentOrganization()->id (bzw. organization_id des Parent-Datensatzes) verwenden —\n"
            . "beim Plattform-Admin-Switch weichen users.organization_id und currentOrganization voneinander ab.\n\n"
            . implode("\n", $violations));
    }
}
