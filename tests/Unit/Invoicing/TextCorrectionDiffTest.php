<?php
/*
 * Created on   : Mon Aug 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TextCorrectionDiffTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Unit\Invoicing;

use App\Services\Invoicing\TextCorrectionDiff;
use PHPUnit\Framework\TestCase;

/**
 * Wort-Diff des Lern-Flows: nur konservative 1:1-Ersetzungen mit
 * Tippfehler-Distanz werden als Wörterbuch-Kandidaten angeboten.
 */
class TextCorrectionDiffTest extends TestCase {
    public function test_erkennt_einfache_wortersetzung(): void {
        $pairs = TextCorrectionDiff::candidates('Serverwartunng durchgeführt', 'Serverwartung durchgeführt');

        $this->assertSame([['wrong' => 'Serverwartunng', 'correct' => 'Serverwartung']], $pairs);
    }

    public function test_ungleiche_tokenzahl_liefert_nichts(): void {
        $this->assertSame([], TextCorrectionDiff::candidates('Server geprüft', 'Server geprüft und neu gestartet'));
        $this->assertSame([], TextCorrectionDiff::candidates('', 'Server geprüft'));
    }

    public function test_satzzeichen_werden_symmetrisch_gestrippt(): void {
        $pairs = TextCorrectionDiff::candidates('Emial geändert.', 'E-Mail geändert.');

        $this->assertSame([['wrong' => 'Emial', 'correct' => 'E-Mail']], $pairs);
    }

    public function test_synonymtausch_wird_verworfen(): void {
        $this->assertSame([], TextCorrectionDiff::candidates('Meeting vorbereitet', 'Besprechung vorbereitet'));
    }

    public function test_reine_case_korrektur_ist_kandidat(): void {
        $pairs = TextCorrectionDiff::candidates('github eingerichtet', 'GitHub eingerichtet');

        $this->assertSame([['wrong' => 'github', 'correct' => 'GitHub']], $pairs);
    }

    public function test_zahlen_und_kurztokens_werden_ignoriert(): void {
        $this->assertSame([], TextCorrectionDiff::candidates('120 Minuten a Stück', '125 Minuten b Stück'));
    }

    public function test_maximal_drei_kandidaten(): void {
        $pairs = TextCorrectionDiff::candidates(
            'worta wortb wortc wortd worte',
            'wortA1 wortB1 wortC1 wortD1 wortE1',
        );

        $this->assertCount(3, $pairs);
    }

    public function test_identische_texte_liefern_nichts(): void {
        $this->assertSame([], TextCorrectionDiff::candidates('Server geprüft', 'Server  geprüft'));
    }
}
