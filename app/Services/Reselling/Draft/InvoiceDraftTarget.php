<?php
/*
 * Created on   : Fri Sep 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : InvoiceDraftTarget.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Reselling\Draft;

use App\Models\{Customer, Organization, User};
use App\Models\Reselling\ResalePeriod;
use Carbon\CarbonImmutable;

/**
 * Ziel eines Rechnungsvorschlags (Feature 152, Review 2026-09-11): der Kern
 * baut die Positionen aus offenen Perioden, das Ziel legt den Entwurf an —
 * lokal als Rechnungsentwurf, extern im Rechnungssystem des Plugins. Die
 * Perioden stempelt und verknüpft der Kern anhand des {@see DraftResult};
 * ein Ziel kennt keine Bezüge. Registrierung über {@see InvoiceDraftTargets}.
 *
 * @phpstan-type DraftLine array{name: string, description: string, quantity: float, unit_name: string, unit_net: float}
 * @phpstan-type DraftEntry array{period: ResalePeriod, line: DraftLine}
 */
interface InvoiceDraftTarget {
    /** Zielschlüssel (`local`, `lexoffice`). */
    public function key(): string;

    /** Bedient dieses Ziel den Rechnungsempfänger (Rechnungshoheit)? */
    public function supports(Customer $recipient): bool;

    /**
     * Vorbedingungen vor dem Bau der Positionen (System aktiv, Kontakt
     * verknüpft) — übersetzte `RuntimeException`, damit der Betreiber die
     * Ursache sieht, auch wenn nichts offen ist.
     *
     * @throws \RuntimeException
     */
    public function ensureAvailable(Organization $organization, Customer $recipient): void;

    /**
     * Entwurf mit einer Position je Periode anlegen. Fachliche Abbrüche
     * (System inaktiv, kein Kontakt) sind übersetzte `RuntimeException`s.
     *
     * @param  list<DraftEntry>  $lines
     *
     * @throws \RuntimeException
     */
    public function draft(Organization $organization, Customer $recipient, array $lines, ?User $user, CarbonImmutable $reference): DraftResult;
}
