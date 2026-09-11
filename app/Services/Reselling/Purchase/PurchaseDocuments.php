<?php
/*
 * Created on   : Fri Sep 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PurchaseDocuments.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Reselling\Purchase;

use App\Models\Organization;
use App\Models\Reselling\ResalePurchaseEntry;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Eingangsbelege des Reselling-Registers (Feature 152, Review 2026-09-11,
 * Einkauf): Composite über alle registrierten {@see PurchaseDocumentSource}s.
 * Singleton (AppServiceProvider) — Ausgaben und Eingangs-E-Rechnungen stehen
 * immer drin, die Lexoffice-Quelle registriert das Plugin beim Boot. Ergebnisse
 * werden zusammengeführt (neueste zuerst), Bezüge über Morph-Typ und ID der
 * jeweiligen Quelle aufgelöst.
 */
final class PurchaseDocuments {
    /** @var array<string, PurchaseDocumentSource> Quellschlüssel → Quelle */
    private array $sources = [];

    /** @var array<string, PurchaseDocumentSource> Morph-Typ → Quelle */
    private array $byMorph = [];

    public function register(PurchaseDocumentSource $source): void {
        $this->sources[$source->key()] = $source;
        $this->byMorph[$source->morphClass()] = $source;
    }

    /** @return list<PurchaseDocumentSource> */
    public function sources(): array {
        return array_values($this->sources);
    }

    public function source(string $key): ?PurchaseDocumentSource {
        return $this->sources[$key] ?? null;
    }

    public function sourceFor(string $morphClass): ?PurchaseDocumentSource {
        return $this->byMorph[$morphClass] ?? null;
    }

    /**
     * Zuteilbare Belege aller Quellen, nach Belegdatum absteigend, auf `$limit` gekürzt.
     *
     * @return Collection<int, PurchaseDocument>
     */
    public function search(Organization $organization, ?string $query, ?CarbonImmutable $from, int $limit): Collection {
        /** @var list<PurchaseDocument> $documents */
        $documents = [];
        foreach ($this->sources as $source) {
            foreach ($source->search($organization, $query, $from, $limit) as $document) {
                $documents[] = $document;
            }
        }
        usort($documents, static fn(PurchaseDocument $a, PurchaseDocument $b): int => ($b->date <=> $a->date) ?: strcmp($b->identity(), $a->identity()));

        return collect(array_slice($documents, 0, $limit));
    }

    /** Beleg aus einem Formularschlüssel — die Quelle, deren Sqid-Alphabet ihn dekodiert, liefert ihn. */
    public function byKey(Organization $organization, string $key): ?PurchaseDocument {
        foreach ($this->sources as $source) {
            $document = $source->byKey($organization, $key);
            if ($document !== null) {
                return $document;
            }
        }

        return null;
    }

    public function byMorph(Organization $organization, string $morphClass, int $id): ?PurchaseDocument {
        return $this->sourceFor($morphClass)?->byMorph($organization, $id);
    }

    /** Beleg einer Einkaufszeile: über den Morph, für Altzeilen über `lexoffice_voucher_id`. */
    public function forEntry(ResalePurchaseEntry $entry, ?Organization $organization = null): ?PurchaseDocument {
        [$morphClass, $id] = $entry->documentReference();
        if ($morphClass === null || $id === null) {
            return null;
        }
        $organization ??= $entry->getRelationValue('organization');
        if (! $organization instanceof Organization) {
            return null;
        }

        return $this->byMorph($organization, $morphClass, $id);
    }

    /**
     * Belege zu Einkaufszeilen, gebündelt je Quelle (eine Abfrage je Morph-Typ)
     * und an die Zeilen gehängt ({@see ResalePurchaseEntry::purchaseDocument()}).
     *
     * @param  iterable<ResalePurchaseEntry>  $entries
     * @return array<string, PurchaseDocument> Identität → Beleg
     */
    public function preload(Organization $organization, iterable $entries): array {
        /** @var array<string, list<int>> $ids */
        $ids = [];
        /** @var list<ResalePurchaseEntry> $all */
        $all = [];
        foreach ($entries as $entry) {
            $all[] = $entry;
            [$morphClass, $id] = $entry->documentReference();
            if ($morphClass !== null && $id !== null && isset($this->byMorph[$morphClass])) {
                $ids[$morphClass][] = $id;
            }
        }
        $documents = [];
        foreach ($ids as $morphClass => $morphIds) {
            foreach ($this->byMorph[$morphClass]->byMorphIds($organization, array_values(array_unique($morphIds))) as $document) {
                $documents[$document->identity()] = $document;
            }
        }
        foreach ($all as $entry) {
            [$morphClass, $id] = $entry->documentReference();
            $entry->attachDocument($morphClass !== null && $id !== null ? ($documents[PurchaseDocument::identityOf($morphClass, $id)] ?? null) : null);
        }

        return $documents;
    }
}
