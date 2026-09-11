<?php
/*
 * Created on   : Thu Sep 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : InvoiceMirrorSource.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Reselling\Mirror;

use App\Models\{Customer, Organization};
use App\Models\Reselling\ResalePeriodLink;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Eine Quelle gespiegelter Ausgangsrechnungen (Feature 152, Review 2026-09-10,
 * Spiegel-Abstraktion): der Kern (Vorschlagslauf, Abgleich, Bezüge) liest nur
 * {@see MirrorLine}s; welche Tabellen dahinter liegen, weiß die Quelle. Der
 * Kern registriert die lokale Rechnungsquelle, Plugins ihre eigene beim Boot
 * ({@see InvoiceMirror::register()}).
 *
 * Empfänger sind Kunden (`recipientCustomerIds`): `null` = alle Empfänger der
 * Organisation, `[]` = keine. Fenster: eine Rechnung zählt ab `$from`, wenn ihr
 * Belegdatum ODER das Ende ihres Leistungszeitraums dort liegt.
 */
interface InvoiceMirrorSource {
    /** Quellschlüssel (`lexoffice`, `local`) — Anzeige über `resale.mirror.source_<key>`. */
    public function key(): string;

    /** Morph-Typ der Positionen in `resale_period_links.linkable_type`. */
    public function morphClass(): string;

    /**
     * Lizenzpositionen gültiger Ausgangsrechnungen (weder Entwurf noch storniert).
     *
     * @param  list<int>|null  $recipientCustomerIds
     * @return Collection<int, MirrorLine>
     */
    public function linesFor(Organization $organization, ?array $recipientCustomerIds, ?CarbonImmutable $from = null, ?CarbonImmutable $to = null): Collection;

    /**
     * Lizenzpositionen stornierter Rechnungen (Erklärung einer Lücke, Storno-Neutralisierung).
     *
     * @param  list<int>|null  $recipientCustomerIds
     * @return Collection<int, MirrorLine>
     */
    public function voidedLines(Organization $organization, ?array $recipientCustomerIds): Collection;

    /**
     * Lizenzpositionen gültiger Gutschriften — negative Bezüge nur von Hand.
     *
     * @param  list<int>  $recipientCustomerIds
     * @return Collection<int, MirrorLine>
     */
    public function creditNoteLines(Organization $organization, array $recipientCustomerIds, ?CarbonImmutable $from = null): Collection;

    /**
     * Gültige Ausgangsrechnungen der Empfänger ab `$from` mit allen Positionen
     * (neueste zuerst) — Rechnungsliste am Abo.
     *
     * @param  list<int>  $recipientCustomerIds
     * @return Collection<int, MirrorVoucher>
     */
    public function vouchersFor(Organization $organization, array $recipientCustomerIds, CarbonImmutable $from, int $limit = 150): Collection;

    /**
     * Rechnungen der Empfänger, deren Positionen noch nicht gespiegelt sind (lokal immer 0).
     *
     * @param  list<int>  $recipientCustomerIds
     */
    public function pendingCount(Organization $organization, array $recipientCustomerIds, ?CarbonImmutable $from = null): int;

    /**
     * Lizenzpositionen gültiger Rechnungen ohne Bezug zu einer Periode
     * (fehlende Abos, falsche Halter), neueste zuerst.
     *
     * @return array{total: int, lines: Collection<int, MirrorLine>}
     */
    public function unlinkedLines(Organization $organization, int $limit): array;

    /**
     * Positionen nach ID — jeden Status, jeden Artikel (Bezüge, Dialoge).
     *
     * @param  list<int>  $ids
     * @return Collection<int, MirrorLine> ID → Position
     */
    public function linesByIds(Organization $organization, array $ids): Collection;

    /** Position aus dem Formularschlüssel ({@see MirrorLine::$key}); null, wenn er nicht zu dieser Quelle gehört. */
    public function lineByKey(Organization $organization, string $key): ?MirrorLine;

    /** Liefert diese Quelle Rechnungen für den Empfänger (verknüpfter Kontakt, lokale Rechnungshoheit)? */
    public function coversRecipient(Organization $organization, Customer $recipient): bool;

    /**
     * Wurde der Rechnungsvorschlag mit dieser Referenz (Perioden-Stempel
     * `draft_reference`: Rechnungsnummer bzw. externe Entwurfs-ID) in dieser
     * Quelle zur Rechnung — also ausgestellt bzw. nicht mehr als Entwurf gespiegelt?
     */
    public function draftBecameInvoice(Organization $organization, string $reference): bool;

    /**
     * Schränkt eine Bezugsabfrage (bereits auf {@see morphClass()} gefiltert)
     * auf Positionen ein, die der Vorschlagslauf neu vergeben darf — Bezüge
     * auf Entwurfspositionen (lokaler Rechnungsentwurf) bleiben stehen.
     *
     * @param  Builder<ResalePeriodLink>  $links
     */
    public function constrainProposalLinks(Organization $organization, Builder $links): void;
}
