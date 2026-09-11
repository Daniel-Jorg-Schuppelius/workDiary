<?php
/*
 * Created on   : Fri Sep 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PurchaseDocumentSource.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Reselling\Purchase;

use App\Models\Organization;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Eine Quelle von Eingangsbelegen (Feature 152, Review 2026-09-11, Einkauf):
 * der Kern liest nur {@see PurchaseDocument}s; welche Tabellen dahinter
 * liegen, weiß die Quelle. Der Kern registriert Ausgaben und Eingangs-
 * E-Rechnungen, Plugins ihre eigene Quelle beim Boot
 * ({@see PurchaseDocuments::register()}).
 *
 * {@see search()} und {@see byKey()} liefern nur zuteilbare Belege (gültig,
 * nicht storniert/archiviert); {@see byMorph()}/{@see byMorphIds()} liefern
 * jeden Beleg — für die Anzeige bestehender Zuteilungen.
 */
interface PurchaseDocumentSource {
    /** Quellschlüssel (`lexoffice`, `expense`, `incoming_einvoice`) — Anzeige über `resale.purchase_document.source.<key>`. */
    public function key(): string;

    /** Morph-Typ der Belege in `resale_purchase_entries.document_type`. */
    public function morphClass(): string;

    /**
     * Zuteilbare Eingangsbelege der Organisation ab `$from` (Belegdatum),
     * neueste zuerst; `$query` filtert nach Nummer und Lieferant.
     *
     * @return Collection<int, PurchaseDocument>
     */
    public function search(Organization $organization, ?string $query, ?CarbonImmutable $from, int $limit): Collection;

    /** Zuteilbarer Beleg aus dem Formularschlüssel ({@see PurchaseDocument::$key}); null, wenn er nicht zu dieser Quelle gehört. */
    public function byKey(Organization $organization, string $key): ?PurchaseDocument;

    /** Beleg nach Morph-ID — jeden Status (Anzeige bestehender Zuteilungen). */
    public function byMorph(Organization $organization, int $id): ?PurchaseDocument;

    /**
     * Belege nach Morph-IDs, eine Abfrage (Einkaufsliste).
     *
     * @param  list<int>  $ids
     * @return Collection<int, PurchaseDocument> ID → Beleg
     */
    public function byMorphIds(Organization $organization, array $ids): Collection;
}
