<?php
/*
 * Created on   : Mon Sep 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SigningRevisionStatus.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Contract;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\{HasLabel, HasStatusTransitions};

/**
 * Unterzeichnungsstatus einer Vertragsfassung (Feature 157, MVP-822).
 * Getrennt vom Vertragsstatus: „unterzeichnet" heißt weder „aktiv" noch
 * „fachlich vollständig" — die Aktivierung läuft weiter über den CLM-Ablauf.
 */
enum SigningRevisionStatus: string implements HasLabel, HasStatusTransitions {
    use HasOptions;

    case Draft = 'draft';
    case Ready = 'ready';
    case PartiallySigned = 'partially_signed';
    case Signed = 'signed';
    case Withdrawn = 'withdrawn';
    case Superseded = 'superseded';

    public function label(): string {
        return (string) __('contract-signing.revision_status.' . $this->value);
    }

    public function tone(): string {
        return match ($this) {
            self::Draft => 'ghost',
            self::Ready => 'info',
            self::PartiallySigned => 'warning',
            self::Signed => 'success',
            self::Withdrawn => 'neutral',
            self::Superseded => 'ghost',
        };
    }

    /** @return list<self> */
    public function allowedTransitions(): array {
        return match ($this) {
            self::Draft => [self::Ready, self::Withdrawn],
            self::Ready => [self::PartiallySigned, self::Signed, self::Withdrawn],
            self::PartiallySigned => [self::Signed, self::Withdrawn],
            self::Signed => [self::Superseded],
            self::Withdrawn, self::Superseded => [],
        };
    }

    /** Noch nicht abgeschlossen — Links dürfen nur hier existieren. */
    public function isOpen(): bool {
        return in_array($this, [self::Draft, self::Ready, self::PartiallySigned], true);
    }

    /** Fassung ist eingefroren (Manifest gebunden). */
    public function isFrozen(): bool {
        return $this !== self::Draft;
    }

    /** Unterschriften dürfen eingehen. */
    public function acceptsSignatures(): bool {
        return in_array($this, [self::Ready, self::PartiallySigned], true);
    }

    /** Vollständig unterzeichnete Fassung (auch eine abgelöste bleibt es). */
    public function isSigned(): bool {
        return in_array($this, [self::Signed, self::Superseded], true);
    }
}
