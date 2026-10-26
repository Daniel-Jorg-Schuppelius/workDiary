<?php
/*
 * Created on   : Mon Oct 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RecurringPlanSource.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Reselling\Plan;

use App\Models\Customer\Customer;
use App\Models\Platform\Organization;
use Illuminate\Support\Collection;

/**
 * Plan-Quelle des Reselling-Registers (Feature 185, MVP-1409): die
 * Serienvorlagen eines Buchhaltungsprogramms, gespiegelt vom Plugin. Der Kern
 * kennt nur diese Sicht; Lexoffice registriert seine Quelle beim Boot über
 * {@see RecurringPlans}. Nur lesend — das Register schreibt keine Vorlage.
 */
interface RecurringPlanSource {
    /** Quellschlüssel (`lexoffice`), Präfix der Vorlagenschlüssel. */
    public function key(): string;

    /** Liefert die Quelle für diese Organisation überhaupt (Plugin aktiv und eingerichtet)? Sonst zählt sie nicht als Plan. */
    public function availableFor(Organization $organization): bool;

    /** @return Collection<int, PlanTemplate> */
    public function templatesFor(Organization $organization): Collection;

    public function templateByKey(Organization $organization, string $key): ?PlanTemplate;

    /** Liefert diese Quelle Vorlagen für den Rechnungsempfänger (Kontakt verknüpft, Programm führt die Rechnungen)? */
    public function coversRecipient(Organization $organization, Customer $recipient): bool;
}
