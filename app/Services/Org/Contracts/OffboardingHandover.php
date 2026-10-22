<?php
/*
 * Created on   : Wed Oct 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : OffboardingHandover.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Org\Contracts;

use App\Models\Platform\User;

/**
 * Erweiterungspunkt des Austritts-Dialogs: ein Fachmodul meldet, was die
 * austretende Person hält, und überträgt es an eine gewählte Nachfolge.
 * Registrierung über `Manifest::extensions()`.
 */
interface OffboardingHandover {
    /** Stabiler Schlüssel des Formularfelds. */
    public function key(): string;

    /** Abschnittstitel mit Anzahl, z. B. „3 Ideenkarten“. */
    public function label(int $count): string;

    public function count(User $member): int;

    /** Überträgt alles an die Nachfolge (auditiert) und liefert die Anzahl. */
    public function handOver(User $member, User $successor, User $actor): int;
}
