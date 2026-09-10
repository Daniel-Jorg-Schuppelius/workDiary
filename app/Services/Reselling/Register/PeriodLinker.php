<?php
/*
 * Created on   : Mon Sep 07 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PeriodLinker.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Reselling\Register;

use App\Enums\Reselling\{LinkOrigin, PeriodStatus};
use App\Models\LexofficeVoucherLine;
use App\Models\Reselling\{ResalePeriod, ResalePeriodLink};
use Illuminate\Support\Facades\DB;

/**
 * Manuelle Bezüge (Feature 152, MVP-761): Rechnungsposition an eine Periode
 * hängen und den Periodenstatus aus der Deckung ableiten. Eine Schreibstelle
 * für Dialog, Schnellzuordnung am Abo und Abgleich je Empfänger — und die
 * eine Stelle für „wie viel einer Position ist schon vergeben".
 */
final class PeriodLinker {
    /**
     * Verbrauch je Position über alle Perioden (Vorschläge eingeschlossen):
     * Lizenzmonate und die Ziele als „Halter · Abo-Kennung · Zeitraum" — bei
     * Partnern mit mehreren Endkunden fehlt sonst der Halter, bei Kunden mit
     * mehreren Verträgen desselben Produkts die Kennung. Bezüge an `$except`
     * zählen nicht (ein erneuter Bezug ersetzt sie).
     *
     * @param  list<int>  $lineIds
     * @return array<int, array{months: float, periods: list<string>}>
     */
    public function consumedMonths(array $lineIds, ?ResalePeriod $except = null, bool $forUpdate = false): array {
        if ($lineIds === []) {
            return [];
        }
        $query = ResalePeriodLink::query()->withoutGlobalScopes()
            ->where('linkable_type', (new LexofficeVoucherLine)->getMorphClass())
            ->whereIn('linkable_id', $lineIds)
            ->with(['period:id,starts_on,ends_on', 'subscription:id,label,quantity,provider,starts_on,customer_id,foreign_customer_id,is_own_holding', 'subscription.customer:id,name', 'subscription.foreignCustomer:id,name']);
        if ($except !== null) {
            $query->where('period_id', '!=', $except->id);
        }
        if ($forUpdate) {
            $query->lockForUpdate();
        }
        $consumed = [];
        foreach ($query->get() as $link) {
            $id = (int) $link->linkable_id;
            $consumed[$id] ??= ['months' => 0.0, 'periods' => []];
            $consumed[$id]['months'] += (float) $link->months;
            $consumed[$id]['periods'][] = self::targetLabel($link);
        }

        return $consumed;
    }

    /** Ziel eines Bezugs: „Halter · ×Menge · Anbieter ab Datum · Zeitraum". */
    public static function targetLabel(ResalePeriodLink $link): string {
        $subscription = $link->subscription;
        $prefix = $subscription !== null ? $subscription->holderLabel() . ' · ' . $subscription->identityLabel() . ' · ' : '';

        return $prefix . $link->period->label();
    }

    /**
     * Noch nicht vergebene Lizenzmonate der Position — Bezüge an DIESER Periode
     * zählen nicht, weil ein erneuter Bezug sie ersetzt. Eine 24er-Position
     * darf nicht mit 48 + 12 verbucht werden. Bei Gutschriften zählt der
     * Betrag der (negativen) Bezüge — das Ergebnis ist immer ≥ 0.
     */
    public function freeMonths(LexofficeVoucherLine $line, ?ResalePeriod $except = null, bool $forUpdate = false): float {
        $line->loadMissing('voucher');
        $used = $this->consumedMonths([$line->id], $except, $forUpdate)[$line->id]['months'] ?? 0.0;
        if ($line->isCreditNote()) {
            $used = abs($used);
        }

        return max(0.0, LicenseMonths::ofLine($line) - $used);
    }

    /**
     * Position an die Periode hängen. Transaktion mit Zeilensperre auf den
     * Bezügen der Position: zwischen „frei?" und „schreiben" darf kein
     * zweiter Nutzer dieselben Monate vergeben.
     *
     * Gutschrift-Positionen (Review 2026-09-10, A3) werden als NEGATIVER Bezug
     * gespeichert — `months`, `quantity` und `amount` unter null; das
     * Vorzeichen setzt der Linker aus dem Belegtyp, `$months` darf positiv
     * (Formulare) oder negativ übergeben werden. Keine Überbuchungsprüfung:
     * die Gutschrift senkt die Deckung, der Status folgt der Summe.
     * Rechnungspositionen brauchen weiterhin `months > 0`.
     */
    public function attach(ResalePeriod $period, LexofficeVoucherLine $line, float $months, ?string $note, ?int $userId): ResalePeriodLink {
        return DB::transaction(function () use ($period, $line, $months, $note, $userId): ResalePeriodLink {
            if (abs($months) < 0.001) {
                throw new \InvalidArgumentException((string) __('resale.credit_notes.error_amount'));
            }
            if ($line->isCreditNote()) {
                $months = -abs($months);
            } else {
                if ($months < 0) {
                    throw new \InvalidArgumentException((string) __('resale.credit_notes.error_positive'));
                }
                $free = $this->freeMonths($line, $period, true);
                if ($months > $free + 0.001) {
                    throw new \InvalidArgumentException((string) __('resale.link.error.exceeds', ['amount' => LicenseMonths::label($free, LicenseMonths::split($line)['months'])]));
                }
            }
            $termMonths = $period->termMonths();
            $link = ResalePeriodLink::query()->updateOrCreate(
                ['period_id' => $period->id, 'linkable_type' => $line->getMorphClass(), 'linkable_id' => $line->id],
                [
                    'organization_id' => $period->organization_id,
                    'subscription_id' => $period->subscription_id,
                    'voucher_number' => $line->voucher->voucher_number,
                    'voucher_date' => $line->voucher->voucher_date,
                    'quantity' => round($months / $termMonths, 3),
                    'months' => round($months, 2),
                    'amount' => $line->unit_net->times(LicenseMonths::unitsFor($line, $months, $termMonths))->withScale(2),
                    'currency' => $line->currency->value,
                    'origin' => LinkOrigin::Manual,
                    'note' => $note,
                    'created_by_user_id' => $userId,
                    'confirmed_at' => now(),
                ],
            );
            $period->unsetRelation('links');
            $this->settle($period, $userId, null);
            // Nutzerentscheidung (Review 2026-09-10, A2) — der Bezug selbst protokolliert nur created/updated.
            $period->audit('resale_period.linked', ['voucher_number' => $link->voucher_number, 'months' => $link->months, 'status_now' => $period->status->value]);

            return $link;
        });
    }

    /**
     * Validierungsregeln für die Deckung: Lizenzen × Monate je Lizenz (Formulare
     * am Abo und im Abgleich) oder rohe Lizenzmonate (Dialog).
     *
     * @return array<string, list<string>>
     */
    public static function amountRules(): array {
        return [
            'months' => ['required_without:licences', 'nullable', 'numeric', 'min:0.01', 'max:100000'],
            'licences' => ['required_without:months', 'nullable', 'numeric', 'min:0.01', 'max:10000'],
            'per_licence' => ['required_with:licences', 'nullable', 'numeric', 'min:0.01', 'max:1200'],
        ];
    }

    /**
     * Lizenzmonate aus der Eingabe: Lizenzen × Monate je Lizenz, sonst Lizenzmonate.
     *
     * @param  array<string, mixed>  $validated
     */
    public static function monthsFrom(array $validated): float {
        if (isset($validated['licences']) && $validated['licences'] !== '') {
            return round((float) $validated['licences'] * (float) ($validated['per_licence'] ?? 1), 2);
        }

        return (float) ($validated['months'] ?? 0);
    }

    /**
     * Status aus der Deckung ableiten; entschieden = Nutzer hat bestätigt/
     * verknüpft. Der Entwurfsstempel bleibt — außer ein entschiedener Bezug
     * zeigt auf dieselbe Rechnung: dann wurde der Entwurf zur Rechnung.
     */
    public function settle(ResalePeriod $period, ?int $userId, ?string $note, bool $decided = true): void {
        $period->load('links');
        $attributes = ['note' => $note !== null && $note !== '' ? $note : $period->note];
        // Verzicht und Einspruch sind Nutzerentscheidungen: ein Bezug, der dort gelöst
        // oder ergänzt wird, kippt sie nicht still — erst „Zurücknehmen" öffnet die Periode.
        if (! in_array($period->status, [PeriodStatus::Waived, PeriodStatus::Disputed], true)) {
            $attributes += [
                'status' => $period->statusFromCoverage($period->coveredMonths()),
                'decided_by_user_id' => $decided ? $userId : null,
                'decided_at' => $decided ? now() : null,
            ];
        }
        if ($period->draftIsInvoiced()) {
            $attributes += ['draft_reference' => null, 'draft_created_at' => null];
        }
        $period->forceFill($attributes)->save();
    }
}
