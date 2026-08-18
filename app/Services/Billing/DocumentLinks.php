<?php
/*
 * Created on   : Sun Aug 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DocumentLinks.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Services\Billing;

use App\Models\{Expense, ExternalReference, LexofficeVoucher};
use App\Plugins\Lexoffice\LexofficePlugin;
use App\Support\Billing\VoucherTypes;
use Illuminate\Support\{Carbon, Collection};

/**
 * Verknüpfung Auslage ↔ Buchhaltungsbeleg (Feature 105, MVP-551).
 *
 * Eine Auslage und die Lieferantenrechnung dazu sind derselbe Aufwand, haben
 * aber keinen strukturellen Bezug: die Quittung wird in workDiary für die
 * Erstattung erfasst und in der Buchhaltung noch einmal. Ohne bestätigte
 * Zuordnung zählte beides doppelt.
 *
 * Getragen wird die Zuordnung von {@see ExternalReference} — polymorph und
 * genau dafür da, deshalb kein eigenes Schema. Vorschläge werden **nie**
 * automatisch übernommen; die Bestätigung ist der fachliche Akt.
 */
class DocumentLinks {
    /** Tage Toleranz zwischen Auslagendatum und Belegdatum im Vorschlag. */
    public const MATCH_DAYS = 14;

    /** Cent-Toleranz beim Betragsvergleich (Rundung fremder Systeme). */
    public const MATCH_CENTS = 2;

    /** Bestätigt die Zuordnung einer Auslage zu einem Buchhaltungsbeleg. */
    public function link(Expense $expense, LexofficeVoucher $voucher): ExternalReference {
        return ExternalReference::updateOrCreate(
            [
                'plugin_id' => LexofficePlugin::ID,
                'external_type' => LexofficePlugin::EXT_TYPE_VOUCHER,
                'referenceable_type' => $expense->getMorphClass(),
                'referenceable_id' => $expense->getKey(),
            ],
            [
                'organization_id' => $expense->organization_id,
                'external_id' => (string) $voucher->external_id,
                'synced_at' => Carbon::now(),
            ],
        );
    }

    /**
     * Hebt die Zuordnung wieder auf — die Auslage zählt danach wieder selbst.
     *
     * Eine **gepushte** Verknüpfung (Feature 106) ist davon ausgenommen: Der
     * Beleg wurde von hier aus angelegt und existiert unwiderruflich — die
     * Verknüpfung zu lösen würde die Dublettenbremse entfernen, den Beleg
     * aber nicht.
     */
    public function unlink(Expense $expense): void {
        $reference = $this->referenceFor($expense);
        if ($reference === null) {
            return;
        }

        if ((bool) ($reference->payload['pushed'] ?? false)) {
            throw new \RuntimeException((string) __('Diese Auslage wurde aktiv als Beleg übergeben — der Beleg existiert unwiderruflich, die Verknüpfung bleibt.'));
        }

        $reference->delete();
    }

    /** Die Verknüpfungszeile selbst, falls vorhanden. */
    public function referenceFor(Expense $expense): ?ExternalReference {
        return ExternalReference::query()
            ->where('plugin_id', LexofficePlugin::ID)
            ->where('external_type', LexofficePlugin::EXT_TYPE_VOUCHER)
            ->where('referenceable_type', $expense->getMorphClass())
            ->where('referenceable_id', $expense->getKey())
            ->first();
    }

    /** Zugeordneter Beleg, falls vorhanden. */
    public function voucherFor(Expense $expense): ?LexofficeVoucher {
        $reference = ExternalReference::query()
            ->where('plugin_id', LexofficePlugin::ID)
            ->where('external_type', LexofficePlugin::EXT_TYPE_VOUCHER)
            ->where('referenceable_type', $expense->getMorphClass())
            ->where('referenceable_id', $expense->getKey())
            ->first();

        if ($reference === null) {
            return null;
        }

        return LexofficeVoucher::query()
            ->where('organization_id', $expense->organization_id)
            ->where('external_id', $reference->external_id)
            ->first();
    }

    /**
     * Belegkandidaten zu einer Auslage: gleicher Betrag (Cent-Toleranz),
     * Belegdatum im Toleranzfenster, Einkaufsbeleg. Bereits anderweitig
     * zugeordnete Belege fallen heraus.
     *
     * @return Collection<int, LexofficeVoucher>
     */
    public function suggestionsFor(Expense $expense, int $limit = 5): Collection {
        $gross = $expense->amount_gross?->toFloat();
        if ($gross === null) {
            return new Collection();
        }

        $tolerance = self::MATCH_CENTS / 100;
        $from = $expense->date->copy()->subDays(self::MATCH_DAYS)->toDateString();
        $to = $expense->date->copy()->addDays(self::MATCH_DAYS)->toDateString();

        /** @var Collection<int, LexofficeVoucher> $candidates */
        $candidates = LexofficeVoucher::query()
            ->where('organization_id', $expense->organization_id)
            ->whereIn('voucher_type', VoucherTypes::EXPENSES)
            ->whereNotIn('voucher_status', VoucherTypes::IGNORED_STATUSES)
            ->whereBetween('voucher_date', [$from, $to])
            ->whereBetween('total_amount', [$gross - $tolerance, $gross + $tolerance])
            ->whereNotIn('external_id', $this->linkedVoucherIds((int) $expense->organization_id, (int) $expense->getKey()))
            ->get();

        // Nächstliegendes Belegdatum zuerst. Bewusst in PHP: das Fenster ist
        // eng, und ein portables Datumsdifferenz-SQL gäbe es für SQLite und
        // MariaDB nur mit Dialektverzweigung.
        return $candidates
            ->sortBy(fn(LexofficeVoucher $voucher): float => abs(
                $expense->date->diffInDays($voucher->voucher_date ?? $expense->date)
            ))
            ->take($limit)
            ->values();
    }

    /**
     * Bereits an ANDERE Auslagen vergebene Beleg-IDs — ein Beleg belegt genau
     * eine Auslage.
     *
     * @return list<string>
     */
    private function linkedVoucherIds(int $organizationId, int $exceptExpenseId): array {
        /** @var list<string> $ids */
        $ids = ExternalReference::query()
            ->where('organization_id', $organizationId)
            ->where('plugin_id', LexofficePlugin::ID)
            ->where('external_type', LexofficePlugin::EXT_TYPE_VOUCHER)
            ->where('referenceable_type', Expense::class)
            ->where('referenceable_id', '!=', $exceptExpenseId)
            ->pluck('external_id')
            ->all();

        return $ids;
    }
}
