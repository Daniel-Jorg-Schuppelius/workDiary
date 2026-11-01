<?php
/*
 * Created on   : Sat Oct 31 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LineFitTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Unit\Reselling;

use App\Models\Reselling\ResalePeriod;
use App\Services\Reselling\Mirror\MirrorLine;
use App\Services\Reselling\Register\LineFit;
use Carbon\CarbonImmutable;
use CommonToolkit\Enums\CurrencyCode;
use CommonToolkit\ValueObjects\Money;
use Tests\TestCase;

/**
 * Passung einer Rechnungsposition zur Periode (Phase 166, MVP-1448): Jahrestag
 * des Abos um einen Monat verschoben, berechnet wird weiter am alten Jahrestag.
 */
class LineFitTest extends TestCase {
    private function line(string $date, ?string $serviceFrom = null, ?string $serviceTo = null): MirrorLine {
        $eur = CurrencyCode::Euro;

        return new MirrorLine(
            'lexoffice', 'lexoffice_voucher_lines', 1, 1, 'k', 'v', 'RE/2023/0100', CarbonImmutable::parse($date), 'paid', false, 1, 'contact:c', 'Test GmbH',
            'lex:1', 'Exchange Online (Plan 1)', true, 'Exchange Online (Plan 1)', null, 12.0, 'Monat', Money::of('3.95', $eur), Money::of('47.40', $eur), $eur,
            $serviceFrom !== null ? CarbonImmutable::parse($serviceFrom) : null, $serviceTo !== null ? CarbonImmutable::parse($serviceTo) : null, null, null, 1, null, null,
        );
    }

    private function period(int $id, string $from, string $to): ResalePeriod {
        $period = new ResalePeriod;
        $period->forceFill(['id' => $id, 'starts_on' => $from, 'ends_on' => $to, 'quantity' => 1]);

        return $period;
    }

    public function test_service_period_offset_decides_between_fits_shifted_and_elsewhere(): void {
        $p2023 = $this->period(1, '2023-12-30', '2024-12-29');
        $p2024 = $this->period(2, '2024-12-30', '2025-12-29');
        $line2023 = $this->line('2023-11-30', '2023-11-30', '2024-11-29');
        $line2024 = $this->line('2024-11-30', '2024-11-30', '2025-11-29');

        $fit = LineFit::of($line2023, $p2023, [$p2023, $p2024]);
        $this->assertSame(LineFit::SHIFTED, $fit->kind);
        $this->assertSame(30, $fit->days);
        $this->assertFalse($fit->needsConfirmation());

        // Die Rechnung des Folgejahres überschneidet die Periode 2023/24 nur um einen Monat und gehört zur Periode 2024/25.
        $wrong = LineFit::of($line2024, $p2023, [$p2023, $p2024]);
        $this->assertSame(LineFit::ELSEWHERE, $wrong->kind);
        $this->assertSame(2, $wrong->better?->id);
        $this->assertTrue($wrong->needsConfirmation());
        $this->assertGreaterThan($fit->rank(), $wrong->rank());
        // Ohne die bessere Periode bleibt es eine bloße Überschneidung — ebenfalls mit Bestätigung.
        $this->assertSame(LineFit::OVERLAP, LineFit::of($line2024, $p2023)->kind);
    }

    public function test_exact_cycle_outside_and_invoice_date_without_service_period(): void {
        $period = $this->period(1, '2024-12-30', '2025-12-29');
        $this->assertSame(LineFit::EXACT, LineFit::of($this->line('2024-12-20', '2024-12-28', '2025-12-27'), $period)->kind);
        // Zweites Jahr einer 24-Monats-Position: der Zyklus beginnt am Jahrestag.
        $this->assertSame(LineFit::EXACT, LineFit::of($this->line('2023-12-28', '2023-12-30', '2025-12-29'), $period)->kind);
        $this->assertSame(LineFit::OUTSIDE, LineFit::of($this->line('2022-11-30', '2022-11-30', '2023-11-29'), $period)->kind);
        $byDate = LineFit::of($this->line('2025-01-20'), $period);
        $this->assertSame(LineFit::SHIFTED, $byDate->kind);
        $this->assertSame(21, $byDate->days);
    }

    public function test_collective_invoice_covering_the_whole_period_fits(): void {
        // Phase 168: Sammelrechnung über 20 Monate (27.01.2017–07.10.2018) für eine Domainperiode ab 26.09.2017 — passt, kein „überschneidet nur“.
        $period = $this->period(1, '2017-09-26', '2018-09-25');
        $fit = LineFit::of($this->line('2017-12-02', '2017-01-27', '2018-10-07'), $period);
        $this->assertSame(LineFit::EXACT, $fit->kind);
        $this->assertFalse($fit->needsConfirmation());
        $this->assertTrue(LineFit::covers($this->line('2017-12-02', '2017-01-27', '2018-10-07'), $period));
    }
}
