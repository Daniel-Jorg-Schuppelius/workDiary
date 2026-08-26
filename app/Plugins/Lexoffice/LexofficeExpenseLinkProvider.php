<?php
/*
 * Created on   : Tue Aug 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LexofficeExpenseLinkProvider.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Lexoffice;

use App\Enums\Expense\ExpenseStatus;
use App\Models\{Expense, ExternalReference, LexofficeVoucher};
use App\Services\Billing\Contracts\ExpenseLinkProvider;
use App\Services\Billing\ExpenseVoucherRef;
use App\Support\Billing\VoucherTypes;
use App\Support\Sqid;
use Illuminate\Support\{Carbon, Collection};
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Lexoffice als führendes Belegsystem der Auslagen (Feature 105 „Zuordnung",
 * Feature 106 „aktiver Push"). Vereint den Code der früheren Kern-Services
 * `DocumentLinks` und `ExpenseVoucherPush`, die beide hart an
 * `LexofficeVoucher` hingen (Vollscan 2026-08-23, B9) — der Kern spricht jetzt
 * nur noch {@see ExpenseLinkProvider}.
 *
 * Eine Auslage und die Lieferantenrechnung dazu sind derselbe Aufwand, haben
 * aber keinen strukturellen Bezug: die Quittung wird in workDiary für die
 * Erstattung erfasst und in der Buchhaltung noch einmal. Ohne bestätigte
 * Zuordnung zählte beides doppelt. Getragen wird die Zuordnung von
 * {@see ExternalReference} — polymorph und genau dafür da, deshalb kein
 * eigenes Schema. Vorschläge werden **nie** automatisch übernommen; die
 * Bestätigung ist der fachliche Akt.
 *
 * Drei Wächter tragen den Push (Feature 106):
 *
 * 1. **Nur genehmigte Auslagen** — der Push ist terminal (Lexoffice kennt für
 *    Belege weder Update noch Delete), deshalb steht die Freigabe davor.
 * 2. **Ohne Kategorie-Zuordnung kein Push** — eine geratene Buchungskategorie
 *    wäre schlimmer als eine Fehlermeldung.
 * 3. **Idempotenz über die ExternalReference** — ein zweiter Klick findet die
 *    Referenz und erzeugt keinen zweiten Beleg.
 */
class LexofficeExpenseLinkProvider implements ExpenseLinkProvider {
    /** Tage Toleranz zwischen Auslagendatum und Belegdatum im Vorschlag. */
    public const MATCH_DAYS = 14;

    /** Cent-Toleranz beim Betragsvergleich (Rundung fremder Systeme). */
    public const MATCH_CENTS = 2;

    public function label(): ?string {
        return 'Lexoffice';
    }

    public function isAvailable(): bool {
        return true; // registriert wird der Provider nur für die aktivierte Organisation
    }

    public function link(Expense $expense, string $voucherKey): ExpenseVoucherRef {
        $voucherId = Sqid::decodeOrNumeric(LexofficeVoucher::class, $voucherKey);
        /** @var LexofficeVoucher $voucher */
        $voucher = LexofficeVoucher::query()
            ->where('organization_id', $expense->organization_id)
            ->findOrFail($voucherId);

        $this->linkVoucher($expense, $voucher);

        return $this->toRef($voucher);
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
            throw new RuntimeException((string) __('Diese Auslage wurde aktiv als Beleg übergeben — der Beleg existiert unwiderruflich, die Verknüpfung bleibt.'));
        }

        $reference->delete();
    }

    public function referenceFor(Expense $expense): ?ExternalReference {
        return ExternalReference::query()
            ->where('plugin_id', LexofficePlugin::ID)
            ->where('external_type', LexofficePlugin::EXT_TYPE_VOUCHER)
            ->where('referenceable_type', $expense->getMorphClass())
            ->where('referenceable_id', $expense->getKey())
            ->first();
    }

    public function voucherFor(Expense $expense): ?ExpenseVoucherRef {
        $voucher = $this->voucherModelFor($expense);

        return $voucher instanceof LexofficeVoucher ? $this->toRef($voucher) : null;
    }

    /**
     * Belegkandidaten zu einer Auslage: gleicher Betrag (Cent-Toleranz),
     * Belegdatum im Toleranzfenster, Einkaufsbeleg. Bereits anderweitig
     * zugeordnete Belege fallen heraus.
     *
     * @return Collection<int, ExpenseVoucherRef>
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
            ->with('supplier')
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
            ->sortBy(fn (LexofficeVoucher $voucher): float => abs(
                $expense->date->diffInDays($voucher->voucher_date ?? $expense->date)
            ))
            ->take($limit)
            ->values()
            ->map(fn (LexofficeVoucher $voucher): ExpenseVoucherRef => $this->toRef($voucher));
    }

    public function canPush(Expense $expense): bool {
        return $expense->status === ExpenseStatus::Approved
            && filled($expense->category?->accounting_category_id)
            && $this->voucherModelFor($expense) === null;
    }

    public function pushVoucher(Expense $expense): ExpenseVoucherRef {
        if ($expense->status !== ExpenseStatus::Approved) {
            throw new RuntimeException((string) __('Nur genehmigte Auslagen können übergeben werden — der Push ist unwiderruflich.'));
        }

        $categoryId = $expense->category?->accounting_category_id;
        if (blank($categoryId)) {
            throw new RuntimeException((string) __('Der Auslagenkategorie fehlt die Buchungskategorie des Buchhaltungssystems — ohne Zuordnung kein Push.'));
        }

        // Idempotenz: Der zweite Klick findet den Beleg des ersten.
        $existing = $this->voucherModelFor($expense);
        if ($existing instanceof LexofficeVoucher) {
            return $this->toRef($existing);
        }

        $result = app(LexofficeService::class)->createExpenseVoucher(
            $expense,
            (string) $categoryId,
            $this->localFilePaths($expense),
        );

        // Spiegelzeile wie beim Voucher-Sync — damit hat der Beleg sofort eine
        // lokale Identität, ohne auf den nächsten Sync-Lauf zu warten.
        $voucher = LexofficeVoucher::query()->create([
            'organization_id' => $expense->organization_id,
            'external_id' => $result['external_id'],
            'voucher_type' => 'purchaseinvoice',
            'voucher_status' => 'open',
            'voucher_date' => $expense->date,
            'total_gross' => $expense->amount_gross?->getAmount(),
            'currency' => $expense->currency->value,
            'synced_at' => Carbon::now(),
        ]);

        // Verknüpfung (Feature 105): ab jetzt führt der Beleg. `pushed`
        // unterscheidet den aktiven Push von der nachträglichen Zuordnung —
        // eine gepushte Verknüpfung darf nicht gelöst werden, der Beleg
        // existiert unwiderruflich.
        $reference = $this->linkVoucher($expense, $voucher);
        $reference->forceFill(['payload' => ['pushed' => true]])->save();

        return $this->toRef($voucher);
    }

    public function wasPushed(Expense $expense): bool {
        $reference = $this->referenceFor($expense);

        return $reference !== null && (bool) ($reference->payload['pushed'] ?? false);
    }

    /** Bestätigt die Zuordnung einer Auslage zu einem Buchhaltungsbeleg. */
    private function linkVoucher(Expense $expense, LexofficeVoucher $voucher): ExternalReference {
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

    private function voucherModelFor(Expense $expense): ?LexofficeVoucher {
        $reference = $this->referenceFor($expense);
        if ($reference === null) {
            return null;
        }

        return LexofficeVoucher::query()
            ->where('organization_id', $expense->organization_id)
            ->where('external_id', $reference->external_id)
            ->first();
    }

    private function toRef(LexofficeVoucher $voucher): ExpenseVoucherRef {
        return new ExpenseVoucherRef(
            externalId: (string) $voucher->external_id,
            key: (string) $voucher->sqid,
            number: $voucher->voucher_number,
            date: $voucher->voucher_date,
            gross: $voucher->total_amount,
            currency: $voucher->currency,
            partyName: $voucher->supplier?->name,
            previewUrl: route('lexoffice.vouchers.preview', $voucher),
        );
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

    /**
     * Belegdateien als lokale Pfade für den Upload.
     *
     * @return list<string>
     */
    private function localFilePaths(Expense $expense): array {
        $paths = [];
        foreach ($expense->attachments as $attachment) {
            $disk = Storage::disk($attachment->disk);
            if ($disk->exists($attachment->path)) {
                $local = $disk->path($attachment->path);
                if (is_file($local)) {
                    $paths[] = $local;
                }
            }
        }

        return $paths;
    }
}
