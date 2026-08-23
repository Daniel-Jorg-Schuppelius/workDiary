<?php
/*
 * Created on   : Fri Aug 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PostingSourceAdapter.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Accounting\Posting;

use App\Enums\Finance\PostingSourceKind;
use App\Models\Organization;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Vertrag eines Quellenadapters (Feature 125, MVP-673).
 *
 * Ein Adapter liest Fachobjekte und übersetzt sie in Buchungsvorschläge. Er
 * trifft **keine** neue steuerrechtliche Entscheidung: Beträge und
 * Steueraufteilung kommen aus dem eingefrorenen Beleg-Snapshot, Konten aus
 * den versionierten Buchungsregeln.
 */
interface PostingSourceAdapter {
    public function kind(): PostingSourceKind;

    /**
     * Buchungsfähige Quellen des Zeitraums (unabhängig davon, ob schon eine
     * Buchung existiert — das entscheidet die Inbox).
     *
     * @return Collection<int, Model>
     */
    public function candidates(Organization $organization, CarbonImmutable $from, CarbonImmutable $to): Collection;

    /** Vorschlag oder — bei fehlenden Voraussetzungen — Blocker. */
    public function proposalFor(Organization $organization, Model $source): PostingProposal;

    /** Idempotenzschlüssel der Quelle (`invoice:42`). */
    public function sourceKey(Model $source): string;
}
