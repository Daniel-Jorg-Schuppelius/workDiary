<?php
/*
 * Created on   : Tue Jul 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RoutePatternValidatorTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Unit\CloudIntake;

use App\Services\CloudIntake\RoutePatternValidator;
use Tests\TestCase;

/**
 * Pfadmuster-Grammatik des Cloud-Dokumenteingangs (Feature 080, MVP-352):
 * `*` = ein Segment, `**` = beliebig viele, Variablen = ein Segment als
 * benannte Gruppe; Vergleich case-insensitiv, Pfade relativ zum Stammordner.
 */
class RoutePatternValidatorTest extends TestCase {
    private RoutePatternValidator $validator;

    protected function setUp(): void {
        parent::setUp();
        $this->validator = new RoutePatternValidator();
    }

    public function test_double_star_matches_across_segments(): void {
        $this->assertNotNull($this->validator->match('Eingangsrechnungen/**', 'Eingangsrechnungen/2026/07/re-123.pdf'));
        $this->assertNotNull($this->validator->match('Eingangsrechnungen/**', 'Eingangsrechnungen/re-123.pdf'));
        $this->assertNull($this->validator->match('Eingangsrechnungen/**', 'Sonstiges/re-123.pdf'));
    }

    public function test_single_star_matches_exactly_one_segment(): void {
        $this->assertNotNull($this->validator->match('Projekte/*/Protokolle/*', 'Projekte/P-100/Protokolle/p1.pdf'));
        $this->assertNull($this->validator->match('Projekte/*/Protokolle/*', 'Projekte/P-100/2026/Protokolle/p1.pdf'));
    }

    public function test_variables_are_extracted_as_named_segments(): void {
        $variables = $this->validator->match(
            'Kunden/{customer_number}/Vertraege/**',
            'Kunden/K-1001/Vertraege/2026/vertrag.pdf',
        );

        $this->assertSame(['customer_number' => 'K-1001'], $variables);
    }

    public function test_matching_is_case_insensitive_and_ignores_leading_slash(): void {
        $this->assertNotNull($this->validator->match('eingangsrechnungen/**', '/Eingangsrechnungen/RE-9.PDF'));
    }

    public function test_regex_metacharacters_in_pattern_are_literal(): void {
        $this->assertNotNull($this->validator->match('Rechnungen (2026)/**', 'Rechnungen (2026)/re.pdf'));
        $this->assertNull($this->validator->match('Rechnungen (2026)/**', 'Rechnungen 2026/re.pdf'));
    }

    public function test_validate_pattern_flags_unknown_and_duplicate_variables(): void {
        $this->assertSame([], $this->validator->validatePattern('Kunden/{customer_number}/**'));

        $errors = $this->validator->validatePattern('X/{foo}/{customer_number}/{customer_number}/***');
        $this->assertCount(3, $errors); // unbekannt + doppelt + ***

        // Unbekannte Variablen matchen nie (bleiben literal) — Aktivierung
        // wird ohnehin durch validatePattern() blockiert.
        $this->assertNull($this->validator->match('X/{foo}/**', 'X/irgendwas/datei.pdf'));
    }

    public function test_first_match_respects_priority_extension_and_size(): void {
        $connectionless = fn (array $attributes) => new \App\Models\CloudIntake\CloudDocumentRoute($attributes);

        $routes = [
            $connectionless(['priority' => 10, 'path_pattern' => 'Eingangsrechnungen/**', 'allowed_extensions' => ['pdf', 'xml'], 'max_file_size' => 1_000, 'target' => 'incoming_invoice', 'active' => true]),
            $connectionless(['priority' => 20, 'path_pattern' => '**', 'target' => 'document', 'active' => true]),
            $connectionless(['priority' => 5, 'path_pattern' => '**', 'target' => 'document', 'active' => false]),
        ];

        // Zu groß für Route 1 → fällt auf Catch-all (Route 2); inaktive Route
        // (Priorität 5) wird übersprungen.
        $match = $this->validator->firstMatch($routes, 'Eingangsrechnungen/re.pdf', 'pdf', 5_000);
        $this->assertNotNull($match);
        $this->assertSame('**', $match['route']->path_pattern);

        // Passt in Route 1 (Endung + Größe).
        $match = $this->validator->firstMatch($routes, 'Eingangsrechnungen/re.pdf', 'PDF', 900);
        $this->assertNotNull($match);
        $this->assertSame('Eingangsrechnungen/**', $match['route']->path_pattern);

        // Falsche Endung für Route 1 → Catch-all.
        $match = $this->validator->firstMatch($routes, 'Eingangsrechnungen/re.docx', 'docx', 900);
        $this->assertNotNull($match);
        $this->assertSame('**', $match['route']->path_pattern);
    }
}
