<?php
/*
 * Created on   : Tue Sep 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : FeatureAvailability.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Lexoffice\Tariff;

use App\Enums\Lexoffice\{LexwareCoverage, LexwareFeature};

/**
 * Auflösung einer Funktion für die Übersicht „Ergänzungen zu Ihrem
 * Lexware-Tarif" (Feature 158): Abdeckung in Lexware, lokale Verfügbarkeit,
 * aktive Zuständigkeit, Übergabewege und ein verständlicher Grund. Anzeige
 * und Backend fragen dieselbe Auflösung — nie verstreute if-Ketten.
 */
final class FeatureAvailability {
    public const STATE_LEXWARE = 'lexware';

    public const STATE_AVAILABLE = 'available';

    public const STATE_SETUP_REQUIRED = 'setup_required';

    public const STATE_CHECK_AVAILABILITY = 'check_availability';

    public const STATE_PLANNED = 'planned';

    /**
     * @param array<string, string> $actionParameters
     * @param list<string> $handoverChannels
     */
    public function __construct(
        public readonly LexwareFeature $feature,
        public readonly LexwareCoverage $coverage,
        public readonly string $state,
        public readonly bool $localAvailable,
        public readonly bool $localActive,
        public readonly ?string $reasonKey,
        public readonly ?string $actionRoute,
        public readonly array $actionParameters,
        public readonly array $handoverChannels,
    ) {}

    public function stateLabel(): string {
        return (string) __('lexware.state.' . $this->state);
    }

    public function stateTone(): string {
        return match ($this->state) {
            self::STATE_LEXWARE => 'info',
            self::STATE_AVAILABLE => 'success',
            self::STATE_SETUP_REQUIRED => 'warning',
            self::STATE_CHECK_AVAILABILITY => 'warning',
            default => 'ghost',
        };
    }

    public function reason(): ?string {
        return $this->reasonKey === null ? null : (string) __($this->reasonKey);
    }

    /** Nur tatsächlich lieferbare, berechtigte Aktionen sind ausführbar. */
    public function isActionable(): bool {
        return $this->state === self::STATE_AVAILABLE && $this->actionRoute !== null;
    }
}
