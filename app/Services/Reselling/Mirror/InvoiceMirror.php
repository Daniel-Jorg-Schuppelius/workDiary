<?php
/*
 * Created on   : Thu Sep 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : InvoiceMirror.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Reselling\Mirror;

use App\Models\{Customer, Organization};
use App\Models\Reselling\{ResalePeriod, ResalePeriodLink};
use App\Services\Reselling\Register\LinkProposer;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Belegspiegel des Reselling-Registers (Feature 152, Review 2026-09-10):
 * Composite über alle registrierten {@see InvoiceMirrorSource}s. Singleton
 * (AppServiceProvider) — die lokale Rechnungsquelle steht immer drin, die
 * Lexoffice-Quelle registriert das Plugin beim Boot. Ein Empfänger mit
 * lokaler Rechnungshoheit und einer mit Lexoffice arbeiten so im selben
 * Register; Ergebnisse werden zusammengeführt, Bezüge über Morph-Typ und ID
 * der jeweiligen Quelle zugeordnet.
 */
final class InvoiceMirror {
    /** @var array<string, InvoiceMirrorSource> Quellschlüssel → Quelle */
    private array $sources = [];

    /** @var array<string, InvoiceMirrorSource> Morph-Typ → Quelle */
    private array $byMorph = [];

    public function register(InvoiceMirrorSource $source): void {
        $this->sources[$source->key()] = $source;
        $this->byMorph[$source->morphClass()] = $source;
    }

    /** @return list<InvoiceMirrorSource> */
    public function sources(): array {
        return array_values($this->sources);
    }

    public function source(string $key): ?InvoiceMirrorSource {
        return $this->sources[$key] ?? null;
    }

    public function sourceFor(string $morphClass): ?InvoiceMirrorSource {
        return $this->byMorph[$morphClass] ?? null;
    }

    /** @return list<string> */
    public function morphClasses(): array {
        return array_keys($this->byMorph);
    }

    /**
     * @param  list<int>|null  $recipientCustomerIds
     * @return Collection<int, MirrorLine>
     */
    public function linesFor(Organization $organization, ?array $recipientCustomerIds, ?CarbonImmutable $from = null, ?CarbonImmutable $to = null): Collection {
        $lines = collect();
        foreach ($this->sources as $source) {
            $lines = $lines->merge($source->linesFor($organization, $recipientCustomerIds, $from, $to));
        }

        return $lines->values();
    }

    /**
     * @param  list<int>|null  $recipientCustomerIds
     * @return Collection<int, MirrorLine>
     */
    public function voidedLines(Organization $organization, ?array $recipientCustomerIds): Collection {
        $lines = collect();
        foreach ($this->sources as $source) {
            $lines = $lines->merge($source->voidedLines($organization, $recipientCustomerIds));
        }

        return $lines->values();
    }

    /**
     * @param  list<int>  $recipientCustomerIds
     * @return Collection<int, MirrorLine>
     */
    public function creditNoteLines(Organization $organization, array $recipientCustomerIds, ?CarbonImmutable $from = null): Collection {
        $lines = collect();
        foreach ($this->sources as $source) {
            $lines = $lines->merge($source->creditNoteLines($organization, $recipientCustomerIds, $from));
        }

        return $lines->values();
    }

    /**
     * Belege aller Quellen, neueste zuerst, auf `$limit` gekürzt.
     *
     * @param  list<int>  $recipientCustomerIds
     * @return Collection<int, MirrorVoucher>
     */
    public function vouchersFor(Organization $organization, array $recipientCustomerIds, CarbonImmutable $from, int $limit = 150): Collection {
        /** @var list<MirrorVoucher> $vouchers */
        $vouchers = [];
        foreach ($this->sources as $source) {
            foreach ($source->vouchersFor($organization, $recipientCustomerIds, $from, $limit) as $voucher) {
                $vouchers[] = $voucher;
            }
        }
        usort($vouchers, static fn(MirrorVoucher $a, MirrorVoucher $b): int => ($b->voucherDate <=> $a->voucherDate) ?: strcmp($b->voucherKey, $a->voucherKey));

        return collect(array_slice($vouchers, 0, $limit));
    }

    /**
     * @param  list<int>  $recipientCustomerIds
     */
    public function pendingCount(Organization $organization, array $recipientCustomerIds, ?CarbonImmutable $from = null): int {
        $count = 0;
        foreach ($this->sources as $source) {
            $count += $source->pendingCount($organization, $recipientCustomerIds, $from);
        }

        return $count;
    }

    /**
     * Lizenzpositionen ohne Bezug über alle Quellen (neueste zuerst, gekürzt).
     *
     * @return array{total: int, lines: Collection<int, MirrorLine>}
     */
    public function unlinkedLines(Organization $organization, int $limit): array {
        $total = 0;
        /** @var list<MirrorLine> $lines */
        $lines = [];
        foreach ($this->sources as $source) {
            $result = $source->unlinkedLines($organization, $limit);
            $total += $result['total'];
            foreach ($result['lines'] as $line) {
                $lines[] = $line;
            }
        }
        usort($lines, static fn(MirrorLine $a, MirrorLine $b): int => ($b->voucherDate <=> $a->voucherDate) ?: ($b->morphId <=> $a->morphId));

        return ['total' => $total, 'lines' => collect(array_slice($lines, 0, $limit))];
    }

    /**
     * Kandidaten für eine Periode: Lizenzpositionen des Empfängers im Fenster
     * der Periode (−90 Tage, Fensterende je Position, Leistungszeitraum),
     * neueste Rechnung zuerst.
     *
     * @return Collection<int, MirrorLine>
     */
    public function candidatesFor(Organization $organization, int $recipientCustomerId, ResalePeriod $period): Collection {
        return $this->linesFor($organization, [$recipientCustomerId], $period->starts_on->subDays(LinkProposer::WINDOW_BEFORE))
            ->filter(static fn(MirrorLine $line): bool => LinkProposer::inWindow($period, $line))
            ->sortBy([static fn(MirrorLine $a, MirrorLine $b): int => ($b->voucherDate <=> $a->voucherDate) ?: ($a->position <=> $b->position)])
            ->values();
    }

    public function lineById(Organization $organization, string $morphClass, int $id): ?MirrorLine {
        $source = $this->sourceFor($morphClass);
        if ($source === null) {
            return null;
        }

        return $source->linesByIds($organization, [$id])->get($id);
    }

    /** Position aus einem Formularschlüssel — die Quelle, deren Sqid-Alphabet ihn dekodiert, liefert sie. */
    public function lineByKey(Organization $organization, string $key): ?MirrorLine {
        foreach ($this->sources as $source) {
            $line = $source->lineByKey($organization, $key);
            if ($line !== null) {
                return $line;
            }
        }

        return null;
    }

    /**
     * Positionen zu Bezügen, gebündelt je Quelle (eine Abfrage je Morph-Typ)
     * und an die Bezüge gehängt ({@see ResalePeriodLink::mirrorLine()}).
     *
     * @param  iterable<ResalePeriodLink>  $links
     * @return array<string, MirrorLine> Identität → Position
     */
    public function preload(Organization $organization, iterable $links): array {
        /** @var array<string, list<int>> $ids */
        $ids = [];
        /** @var list<ResalePeriodLink> $all */
        $all = [];
        foreach ($links as $link) {
            $all[] = $link;
            if (isset($this->byMorph[$link->linkable_type])) {
                $ids[$link->linkable_type][] = (int) $link->linkable_id;
            }
        }
        $lines = [];
        foreach ($ids as $morphClass => $morphIds) {
            foreach ($this->byMorph[$morphClass]->linesByIds($organization, array_values(array_unique($morphIds))) as $line) {
                $lines[$line->identity()] = $line;
            }
        }
        foreach ($all as $link) {
            $link->attachMirrorLine($lines[MirrorLine::identityOf((string) $link->linkable_type, (int) $link->linkable_id)] ?? null);
        }

        return $lines;
    }

    public function isCreditNote(Organization $organization, string $morphClass, int $id): bool {
        $line = $this->lineById($organization, $morphClass, $id);

        return $line !== null && $line->isCreditNote;
    }

    /** Liefert irgendeine Quelle Rechnungen für den Empfänger? */
    public function coversRecipient(Organization $organization, Customer $recipient): bool {
        foreach ($this->sources as $source) {
            if ($source->coversRecipient($organization, $recipient)) {
                return true;
            }
        }

        return false;
    }

    /** Wurde der Entwurf mit dieser Referenz in irgendeiner Quelle zur Rechnung? */
    public function draftBecameInvoice(Organization $organization, string $reference): bool {
        foreach ($this->sources as $source) {
            if ($source->draftBecameInvoice($organization, $reference)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Bezüge auf Spiegelpositionen, die der Vorschlagslauf ersetzen darf —
     * je Quelle ihr Morph-Typ samt Einschränkung (lokale Entwürfe bleiben).
     *
     * @param  Builder<ResalePeriodLink>  $links
     * @return Builder<ResalePeriodLink>
     */
    public function scopeProposalLinks(Organization $organization, Builder $links): Builder {
        return $links->where(function (Builder $outer) use ($organization): void {
            if ($this->sources === []) {
                $outer->whereRaw('1 = 0');

                return;
            }
            foreach ($this->sources as $source) {
                $outer->orWhere(function (Builder $inner) use ($organization, $source): void {
                    $inner->where('linkable_type', $source->morphClass());
                    $source->constrainProposalLinks($organization, $inner);
                });
            }
        });
    }
}
