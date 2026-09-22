<?php
/*
 * Created on   : Mon Sep 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SignatureMethod.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Contract;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/**
 * Art des Unterzeichnungsnachweises (Feature 157): gezeichnete oder
 * getippte Browser-Signatur (einfache elektronische Unterzeichnung, keine
 * QES) bzw. hochgeladenes, intern geprüftes PDF.
 */
enum SignatureMethod: string implements HasLabel {
    use HasOptions;

    case Drawn = 'drawn';
    case Typed = 'typed';
    case Upload = 'upload';

    public function label(): string {
        return (string) __('contract-signing.method.' . $this->value);
    }

    /** Browser-Wege sind sofort wirksam; der Upload braucht die Prüfung. */
    public function requiresReview(): bool {
        return $this === self::Upload;
    }
}
