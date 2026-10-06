<?php
/*
 * Created on   : Mon Oct 05 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : QuoteStatus.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Sales;

use App\Enums\Concerns\{HasOptions, HasTransitions};
use App\Enums\Contracts\HasStatusTransitions;

/**
 * Lebenszyklus eines Angebots (Feature 066, MVP-170): Freigabe, Versand,
 * Entscheidung. Nach dem Versand wird versioniert statt geändert. Der
 * Import übernimmt den Stand aus Altdaten ohne Übergangsprüfung.
 */
enum QuoteStatus: string implements HasStatusTransitions {
    use HasOptions;
    use HasTransitions;

    case Draft = 'draft';
    case Approved = 'approved';
    case Sent = 'sent';
    case Accepted = 'accepted';
    case PartiallyAccepted = 'partially_accepted';
    case Rejected = 'rejected';
    case Expired = 'expired';

    /**
     * Freigegeben oder versandt, noch ohne Entscheidung: Bindefrist und Nachfassen laufen.
     *
     * @return list<self>
     */
    public static function pending(): array {
        return [self::Approved, self::Sent];
    }

    /**
     * Noch nicht entschieden — zählt nicht zur Trefferquote.
     *
     * @return list<self>
     */
    public static function open(): array {
        return [self::Draft, ...self::pending()];
    }

    /**
     * Ganz oder in Teilen angenommen.
     *
     * @return list<self>
     */
    public static function won(): array {
        return [self::Accepted, self::PartiallyAccepted];
    }

    /**
     * Im Import zulässig — Freigabe und Teilannahme entstehen nur in der App.
     *
     * @return list<self>
     */
    public static function importable(): array {
        return [self::Draft, self::Sent, self::Accepted, self::Rejected, self::Expired];
    }

    public function isPending(): bool {
        return in_array($this, self::pending(), true);
    }

    public function isWon(): bool {
        return in_array($this, self::won(), true);
    }

    /** Der Kunde hat entschieden (das Portal zeigt nur noch das Ergebnis). */
    public function isDecided(): bool {
        return $this->isWon() || $this === self::Rejected;
    }

    /** Versandte, abgelehnte und abgelaufene Angebote bekommen eine neue Version. */
    public function isVersionable(): bool {
        return in_array($this, [self::Sent, self::Rejected, self::Expired], true);
    }

    public function label(): string {
        return (string) __('values.' . $this->value);
    }

    /** @return list<self> */
    public function allowedTransitions(): array {
        return match ($this) {
            self::Draft => [self::Approved],
            self::Approved => [self::Sent, self::Expired],
            self::Sent => [self::Accepted, self::PartiallyAccepted, self::Rejected, self::Expired],
            self::Accepted, self::PartiallyAccepted, self::Rejected, self::Expired => [],
        };
    }
}
