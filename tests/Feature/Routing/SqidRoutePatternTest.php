<?php
/*
 * Created on   : Sat May 30 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SqidRoutePatternTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Routing;

use App\Models\Project;
use App\Services\SqidEncoder;
use Tests\TestCase;

/**
 * Sichert die Kopplung zwischen dem `project`-Route-Pattern
 * ([A-Za-z0-9]+, siehe routes/web.php + routes/api.php) und dem
 * konfigurierten Sqid-Alphabet ab: Wird das Alphabet auf Zeichen außerhalb
 * [A-Za-z0-9] umgestellt, würden Projekt-Sqids das Pattern nicht mehr
 * passieren (404). Dieser Test schlägt dann fehl und macht die Annahme
 * explizit, statt sie still brechen zu lassen.
 */
class SqidRoutePatternTest extends TestCase {
    private const PROJECT_PATTERN = '/^[A-Za-z0-9]+$/';

    public function test_configured_alphabet_is_alphanumeric(): void {
        $alphabet = (string) config('sqids.alphabet');

        $this->assertNotSame('', $alphabet);
        $this->assertMatchesRegularExpression(
            self::PROJECT_PATTERN,
            $alphabet,
            'config(sqids.alphabet) enthält Zeichen außerhalb [A-Za-z0-9] — '
                . 'das project-Route-Pattern [A-Za-z0-9]+ würde Projekt-Sqids nicht mehr matchen.',
        );
    }

    public function test_generated_project_sqids_match_the_route_pattern(): void {
        $encoder = app(SqidEncoder::class);

        foreach ([1, 2, 42, 1000, 999_999_999] as $id) {
            $sqid = $encoder->encode(Project::class, $id);

            $this->assertMatchesRegularExpression(
                self::PROJECT_PATTERN,
                $sqid,
                "Project-Sqid für ID {$id} matcht das Route-Pattern nicht: {$sqid}",
            );
        }
    }
}
