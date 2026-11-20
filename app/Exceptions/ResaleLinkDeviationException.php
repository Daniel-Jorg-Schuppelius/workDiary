<?php
/*
 * Created on   : Fri Nov 20 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ResaleLinkDeviationException.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Exceptions;

/**
 * Ein Rechnungsbezug weicht in Artikel oder Betrag vom Abo ab und trägt keine
 * Begründung (MVP-1768). Der Aufrufer zeigt den Fehler am Begründungsfeld.
 */
final class ResaleLinkDeviationException extends \InvalidArgumentException {
    public function __construct(public readonly string $kind) {
        parent::__construct((string) __('resale.deviation.needs_reason.' . $kind));
    }
}
