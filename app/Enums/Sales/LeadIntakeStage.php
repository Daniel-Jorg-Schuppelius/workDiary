<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LeadIntakeStage.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Sales;

use App\Enums\Concerns\{HasOptions, HasTransitions};
use App\Enums\Contracts\{HasLabel, HasStatusTransitions};

/**
 * Stufe einer Aufnahmeakte (Feature 200, MVP-1641): Anfrage → Gespräch →
 * Unterlagen → Entscheidung → Einrichtung, danach „eingerichtet“ (Lead ist
 * Kunde) oder „abgelehnt“. Die ersten drei Stufen sind frei wählbar, die
 * Entscheidung führt nur über den Dienst (Annahme → Einrichtung, Ablehnung →
 * abgelehnt); die Einrichtung endet mit der Umwandlung des Leads.
 */
enum LeadIntakeStage: string implements HasLabel, HasStatusTransitions {
    use HasOptions;
    use HasTransitions;

    case Inquiry = 'inquiry';
    case Interview = 'interview';
    case Documents = 'documents';
    case Decision = 'decision';
    case Setup = 'setup';
    case Completed = 'completed';
    case Rejected = 'rejected';

    public function label(): string {
        return (string) __('enums.sales.lead_intake_stage.' . $this->value);
    }

    public function tone(): string {
        return match ($this) {
            self::Inquiry => 'info',
            self::Interview, self::Documents => 'primary',
            self::Decision => 'warning',
            self::Setup => 'accent',
            self::Completed => 'success',
            self::Rejected => 'ghost',
        };
    }

    /** Läuft die Aufnahme noch (weder eingerichtet noch abgelehnt)? */
    public function isOpen(): bool {
        return $this !== self::Completed && $this !== self::Rejected;
    }

    /** Darf der Bogen noch bearbeitet werden (vor der Entscheidung)? */
    public function isEditable(): bool {
        return in_array($this, [self::Inquiry, self::Interview, self::Documents, self::Decision], true);
    }

    /**
     * Stufen der Leiste in der Akte und der Kachel „Aufnahmen“.
     *
     * @return list<self>
     */
    public static function pipeline(): array {
        return [self::Inquiry, self::Interview, self::Documents, self::Decision, self::Setup];
    }

    /** @return list<string> Speicherwerte der offenen Stufen */
    public static function openValues(): array {
        return array_map(static fn (self $stage): string => $stage->value, self::pipeline());
    }

    /** @return list<self> */
    public function allowedTransitions(): array {
        return match ($this) {
            self::Inquiry => [self::Interview, self::Documents, self::Decision],
            self::Interview => [self::Inquiry, self::Documents, self::Decision],
            self::Documents => [self::Interview, self::Decision],
            self::Decision => [self::Documents, self::Setup, self::Rejected],
            self::Setup => [self::Completed],
            self::Completed, self::Rejected => [],
        };
    }
}
