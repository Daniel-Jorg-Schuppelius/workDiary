<?php
/*
 * Created on   : Tue Nov 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AiConnectionChoice.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Ai;

use App\Models\Ai\AiProviderConnection;
use App\Models\Platform\Organization;

/**
 * Verbindungswahl des Nutzers für einen Aufruf (Feature 025, MVP-1590): Der
 * Controller merkt die im Formular gewählte Verbindung je Capability, der
 * {@see AiInvocationService} nimmt sie als gewünschte Verbindung. Ob die
 * Organisation die Wahl erlaubt und die Verbindung zulässig ist, prüft allein
 * der {@see AiRoutingResolver}.
 *
 * Scoped gebunden: die Wahl gilt für einen Request und wandert nie in den nächsten.
 */
final class AiConnectionChoice {
    /** @var array<string, int> Capability → Verbindung */
    private array $chosen = [];

    /** @var array<string, list<AiProviderConnection>> "<Organisation>|<Capability>" → wählbare Verbindungen */
    private array $options = [];

    public function __construct(private readonly AiRoutingResolver $resolver) {}

    /**
     * Wählbare Verbindungen für die Auswahl in der Vorschlagskomponente — je
     * Request einmal je Capability, auch wenn eine Seite viele Vorschläge zeigt.
     *
     * @return list<AiProviderConnection>
     */
    public function optionsFor(Organization $organization, string $capability): array {
        return $this->options[$organization->id . '|' . $capability] ??= $this->resolver->userChoices($organization, $capability);
    }

    public function choose(string $capability, int $connectionId): void {
        $this->chosen[$capability] = $connectionId;
    }

    public function for(string $capability): ?int {
        return $this->chosen[$capability] ?? null;
    }
}
