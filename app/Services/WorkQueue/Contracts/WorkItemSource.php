<?php
/*
 * Created on   : Fri Oct 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : WorkItemSource.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\WorkQueue\Contracts;

use App\Enums\WorkQueue\WorkItemCategory;
use App\Models\Platform\{Organization, User};
use App\Services\WorkQueue\Dto\WorkItem;

/**
 * Erweiterungspunkt des Arbeitsvorrats (Feature 179): ein Modul meldet seine
 * offenen Vorgänge über `Manifest::extensions()`, ein Plugin über
 * `ModuleRegistry::contribute()`. Die Quelle bleibt führend — der
 * Arbeitsvorrat liest nur und verlinkt auf ihre Bearbeitungsfläche.
 */
interface WorkItemSource {
    /** Stabiler Schlüssel, zugleich Filterwert der Liste. */
    public function key(): string;

    /** Bezeichnung der Quelle in Filter und Zeile. */
    public function label(): string;

    public function category(): WorkItemCategory;

    /** Darf der Nutzer die Fläche der Quelle öffnen (Modul, Tarif, Recht)? Sonst fragt der Arbeitsvorrat nicht. */
    public function available(User $viewer): bool;

    /**
     * Offene Vorgänge der Organisation, die den Nutzer betreffen — nur, was er
     * auf der Fläche der Quelle auch sähe.
     *
     * @return iterable<WorkItem>
     */
    public function items(User $viewer, Organization $organization): iterable;
}
