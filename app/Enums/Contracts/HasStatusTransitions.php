<?php
/*
 * Created on   : Sat Jul 18 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : HasStatusTransitions.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Contracts;

/**
 * Typanker für Status-Enums mit Statusmaschine (Vollaudit 2026-07, M44/N30):
 * die Service-Guards ({@see \App\Services\Concerns\AssertsStatusTransition},
 * {@see \App\Services\Isms\Concerns\AssertsIsmsTransition}) arbeiten gegen
 * dieses Interface statt gegen 17 unverbundene Enum-Klassen.
 */
interface HasStatusTransitions extends HasLabel {
    /**
     * Erlaubte Folgezustände des aktuellen Status.
     *
     * @return list<static>
     */
    public function allowedTransitions(): array;
}
