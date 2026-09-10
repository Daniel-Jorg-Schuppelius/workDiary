<?php
/*
 * Created on   : Thu Sep 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LexofficeRepairResaleLinksCommand.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Lexoffice\Console;

use App\Console\Concerns\IteratesOrganizations;
use App\Models\{LexofficeVoucher, LexofficeVoucherLine};
use App\Models\Reselling\ResalePeriodLink;
use App\Plugins\Lexoffice\LexofficeVoucherLineSync;
use Illuminate\Console\Command;
use Illuminate\Database\Query\Builder as QueryBuilder;

/**
 * Verwaiste Rechnungsbezüge des Reselling-Registers reparieren (Feature 152,
 * Review 2026-09-10 B1): `resale_period_links` zeigt per Morph ohne FK auf
 * `lexoffice_voucher_lines`; ein früherer Spiegel-Refresh (delete + create)
 * hat die Positions-IDs erneuert. Umhängen über Rechnungsnummer → Rechnung
 * im Spiegel → eindeutige Lizenzposition. Was nicht eindeutig ist, wird nur
 * gelistet — gelöscht wird nichts, das entscheidet der Betreiber.
 */
class LexofficeRepairResaleLinksCommand extends Command {
    use IteratesOrganizations;

    protected $signature = 'lexoffice:repair-resale-links ' . self::ORGANIZATION_OPTION . ' {--dry-run : Nur anzeigen, nichts umhängen}';

    protected $description = 'Verwaiste Rechnungsbezüge des Reselling-Registers auf die passende Spiegelposition umhängen';

    private string $morph;

    public function handle(): int {
        $this->morph = (new LexofficeVoucherLine)->getMorphClass();
        $dryRun = (bool) $this->option('dry-run');

        $repaired = 0;
        $unresolved = 0;
        foreach ($this->organizationsToProcess() as $organization) {
            $orphans = ResalePeriodLink::query()->withoutGlobalScopes()
                ->where('organization_id', $organization->id)
                ->where('linkable_type', $this->morph)
                ->whereNotIn('linkable_id', static fn(QueryBuilder $q) => $q->select('id')->from('lexoffice_voucher_lines'))
                ->with(['period', 'subscription'])
                ->orderBy('id')
                ->get();
            if ($orphans->isEmpty()) {
                continue;
            }
            $this->info(sprintf('Organisation #%d (%s): %d verwaiste Rechnungsbezüge', $organization->id, $organization->name, $orphans->count()));
            foreach ($orphans as $link) {
                $label = sprintf('Bezug #%d, Periode %s, Rechnung %s', $link->id, $link->period->label(), $link->voucher_number ?? '—');
                [$line, $reason] = $this->resolve($link, $organization->id);
                if ($line === null) {
                    $this->warn(sprintf('  nicht auflösbar: %s — %s', $label, $reason));
                    $unresolved++;

                    continue;
                }
                if (! $dryRun) {
                    $link->forceFill(['linkable_id' => $line->id])->save();
                }
                $this->line(sprintf('  %s: %s → Position %d (#%d) „%s"', $dryRun ? 'würde umgehängt' : 'umgehängt', $label, $line->position, $line->id, $line->name));
                $repaired++;
            }
        }

        $this->info(sprintf('%s: %d umgehängt, %d nicht auflösbar.', $dryRun ? 'Probelauf' : 'Reparatur', $repaired, $unresolved));

        return self::SUCCESS;
    }

    /**
     * Zielposition: Rechnung (org-gescopt über die Nummer) → Lizenzpositionen
     * (mit Lexoffice-Artikel); bei mehreren entscheidet der Artikel des Abos,
     * ersatzweise der Positionsname in der Bemerkung des Bezugs.
     *
     * @return array{0: LexofficeVoucherLine|null, 1: string}
     */
    private function resolve(ResalePeriodLink $link, int $organizationId): array {
        $number = trim((string) $link->voucher_number);
        if ($number === '') {
            return [null, 'keine Rechnungsnummer am Bezug'];
        }
        $vouchers = LexofficeVoucher::query()->withoutGlobalScopes()
            ->where('organization_id', $organizationId)
            ->whereIn('voucher_type', LexofficeVoucherLineSync::VOUCHER_TYPES)
            ->where('voucher_number', $number)
            ->get();
        if ($vouchers->count() !== 1) {
            return [null, $vouchers->isEmpty() ? 'Rechnung nicht im Spiegel' : 'Rechnungsnummer mehrfach im Spiegel'];
        }
        /** @var LexofficeVoucher $voucher */
        $voucher = $vouchers->first();
        $lines = LexofficeVoucherLine::query()->withoutGlobalScopes()
            ->where('voucher_id', $voucher->id)
            ->whereNotNull('lexoffice_article_id')
            ->orderBy('position')
            ->get();
        if ($lines->isEmpty()) {
            return [null, 'Rechnung ohne Lizenzposition (Positionen fehlen? lexoffice:sync-voucher-lines)'];
        }
        if ($lines->count() > 1) {
            $articleId = $link->subscription?->lexoffice_article_id;
            $note = mb_strtolower(trim((string) $link->note));
            $candidates = $articleId === null ? $lines : $lines->where('lexoffice_article_id', $articleId);
            if ($candidates->count() !== 1 && $note !== '') {
                $candidates = $lines->filter(static fn(LexofficeVoucherLine $l): bool => $l->name !== '' && str_contains($note, mb_strtolower($l->name)));
            }
            if ($candidates->count() !== 1) {
                return [null, sprintf('%d Lizenzpositionen, keine eindeutig (Abo-Artikel/Bemerkung)', $lines->count())];
            }
            $lines = $candidates;
        }
        /** @var LexofficeVoucherLine $line */
        $line = $lines->first();

        // Unique (period_id, linkable_type, linkable_id): hängt die Position
        // schon an der Periode, wäre der verwaiste Bezug ein Duplikat.
        $taken = ResalePeriodLink::query()->withoutGlobalScopes()
            ->where('period_id', $link->period_id)
            ->where('linkable_type', $this->morph)
            ->where('linkable_id', $line->id)
            ->exists();
        if ($taken) {
            return [null, sprintf('Position %d (#%d) ist an der Periode bereits verknüpft — Bezug wäre ein Duplikat', $line->position, $line->id)];
        }

        return [$line, ''];
    }
}
