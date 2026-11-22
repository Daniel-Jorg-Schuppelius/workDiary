<?php
/*
 * Created on   : Sun Nov 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : NumberFormatWindow.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Numbering;

use App\Enums\Numbering\NumberScope;
use App\Models\Numbering\{NumberFormat, NumberSequence};

/**
 * Formate und Zählerstände einer Organisation für die Übersicht „Nummernkreise“,
 * die je Kreis mehrfach danach fragt. Scoped gebunden und nur zwischen
 * {@see open()} und {@see close()} wirksam — die Nummernvergabe liest immer
 * frisch und unter Sperre.
 */
final class NumberFormatWindow {
    private ?int $organizationId = null;

    /** @var array<string, NumberFormat> Kreis → Format */
    private array $formats = [];

    /** @var array<string, NumberSequence> "<Kreis>|<Periode>" → Zähler */
    private array $sequences = [];

    public function open(int $organizationId): void {
        $this->organizationId = $organizationId;
        $this->formats = [];
        $this->sequences = [];
        foreach (NumberFormat::query()->where('organization_id', $organizationId)->orderBy('id')->get() as $format) {
            $this->formats[(string) $format->getRawOriginal('scope')] ??= $format;
        }
        foreach (NumberSequence::query()->where('organization_id', $organizationId)->orderBy('id')->get() as $sequence) {
            $this->sequences[$sequence->getRawOriginal('scope') . '|' . ($sequence->period ?? '')] ??= $sequence;
        }
    }

    public function close(): void {
        $this->organizationId = null;
        $this->formats = [];
        $this->sequences = [];
    }

    /** Gespeichertes Format des Kreises; false, wenn das Fenster die Organisation nicht deckt. */
    public function format(int $organizationId, NumberScope $scope): NumberFormat|false|null {
        return $this->organizationId === $organizationId ? ($this->formats[$scope->value] ?? null) : false;
    }

    /** Zähler des Kreises in der Periode (null = ohne Jahreswechsel); false, wenn das Fenster nicht deckt. */
    public function sequence(int $organizationId, NumberScope $scope, ?string $period): NumberSequence|false|null {
        return $this->organizationId === $organizationId ? ($this->sequences[$scope->value . '|' . ($period ?? '')] ?? null) : false;
    }
}
