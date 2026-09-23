<?php
/*
 * Created on   : Wed Sep 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ClubFeeRunService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Club;

use App\Enums\Club\{ClubFeeClaimStatus, ClubFeeRunStatus};
use App\Enums\Finance\BillingMode;
use App\Models\Club\{ClubFeeAccount, ClubFeeClaim, ClubFeeClaimItem, ClubFeeRun};
use App\Models\{DocumentDispatch, Organization, User};
use App\Services\Concerns\{AssertsStatusTransition, AssignsSequentialNo};
use Carbon\CarbonImmutable;
use CommonToolkit\Enums\CurrencyCode;
use CommonToolkit\Helper\Data\EmailHelper;
use CommonToolkit\ValueObjects\Money;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\{Auth, DB, Mail};
use Illuminate\Validation\ValidationException;

/**
 * Beitragslauf, Forderungen, Mitteilung und Korrektur (Feature 159,
 * MVP-850) — einzige Schreibstelle. Die Freigabe erzeugt je Konto genau eine
 * Forderung aus der eingefrorenen Vorschau; der fachliche Quellschlüssel je
 * Position verhindert Doppelungen bei Wiederholung, Nachholung und
 * parallelem Lauf (Unique je Organisation). Bei extern geführter Abrechnung
 * bleibt die lokale Freigabe gesperrt — Vorschau und Übergabeliste nicht.
 */
class ClubFeeRunService {
    use AssertsStatusTransition;
    use AssignsSequentialNo;

    public function __construct(
        private readonly ClubFeeCalculator $calculator,
        private readonly ClubFeeService $fees,
    ) {}

    /** Rechnungshoheit der Organisation: extern → keine lokale Freigabe, nur Vorschau/Übergabe. */
    public function externalBillingMode(Organization $organization): ?BillingMode {
        $mode = BillingMode::tryFrom((string) data_get($organization->settings, 'billing_mode', ''));

        return $mode !== null && $mode->isExternal() ? $mode : null;
    }

    /** Entwurf anlegen oder neu berechnen: Vorschau und Fehler werden eingefroren. */
    public function prepare(Organization $organization, int $year, int $month, ?User $actor = null, ?ClubFeeRun $run = null): ClubFeeRun {
        return DB::transaction(function () use ($organization, $year, $month, $actor, $run): ClubFeeRun {
            $result = $this->calculator->calculateMonth($organization, $year, $month);
            $positions = $result['positions']->map(fn(ClubFeePosition $p): array => $p->toArray())->values()->all();
            $total = $result['positions']->isEmpty()
                ? Money::zero($this->fees->defaultCurrency())
                : Money::sum($result['positions']->map(fn(ClubFeePosition $p): Money => $p->amount)->all());
            $values = [
                'positions' => $positions,
                'issues' => $result['issues'],
                'total' => $total,
                'currency' => $total->getCurrency()->value,
                'calculated_at' => now(),
            ];
            if ($run !== null) {
                if (! $run->isDraft()) {
                    throw ValidationException::withMessages(['status' => __('club.fees.error.run_not_draft')]);
                }
                $run->update($values);
                $run->audit('club.fee.runRecalculated', ['positions' => count($positions), 'issues' => count($result['issues'])]);

                return $run->refresh();
            }
            $created = ClubFeeRun::query()->create([
                'organization_id' => $organization->id,
                'year' => $year,
                'month' => $month,
                'status' => ClubFeeRunStatus::Draft->value,
                'created_by_user_id' => $actor?->id,
            ] + $values);
            $created->audit('club.fee.runCreated', ['year' => $year, 'month' => $month, 'positions' => count($positions)]);

            return $created;
        });
    }

    /**
     * Freigabe: genau eine Forderung je Konto und Lauf aus den noch nicht
     * beanspruchten Quellschlüsseln. Fehler in der Vorschau und externe
     * Rechnungshoheit sperren; ein zweiter Aufruf erzeugt nichts.
     *
     * @return Collection<int, ClubFeeClaim>
     */
    public function release(ClubFeeRun $run, User $actor): Collection {
        return DB::transaction(function () use ($run, $actor): Collection {
            /** @var ClubFeeRun $run */
            $run = ClubFeeRun::query()->whereKey($run->id)->lockForUpdate()->firstOrFail();
            if ($run->status === ClubFeeRunStatus::Released) {
                return $run->claims()->get();
            }
            $this->assertStatusTransition($run->status, ClubFeeRunStatus::Released);
            $organization = Organization::query()->findOrFail($run->organization_id);
            $external = $this->externalBillingMode($organization);
            if ($external !== null) {
                throw ValidationException::withMessages(['status' => __('club.fees.error.external_billing', ['mode' => $external->label()])]);
            }
            if ($run->hasIssues()) {
                throw ValidationException::withMessages(['status' => __('club.fees.error.run_has_issues')]);
            }

            $positions = collect($run->positions ?? []);
            $keys = $positions->pluck('source_key')->all();
            $claimed = $keys === [] ? [] : ClubFeeClaimItem::query()
                ->where('organization_id', $run->organization_id)
                ->whereIn('source_key', $keys)
                ->pluck('source_key')
                ->all();
            $fresh = $positions->reject(fn(array $p): bool => in_array($p['source_key'], $claimed, true));
            $accounts = ClubFeeAccount::query()->whereIn('id', $fresh->pluck('account_id')->unique())->with('customer')->get()->keyBy('id');

            $claims = collect();
            $issuedOn = CarbonImmutable::today();
            foreach ($fresh->groupBy('account_id') as $accountId => $rows) {
                /** @var ClubFeeAccount|null $account */
                $account = $accounts->get((int) $accountId);
                if ($account === null) {
                    continue;
                }
                $currency = CurrencyCode::tryFrom((string) ($rows->first()['currency'] ?? '')) ?? $this->fees->defaultCurrency();
                $total = Money::sum($rows->map(fn(array $p): Money => Money::of((string) $p['amount'], $currency))->all(), $currency);
                $sequence = $this->nextNo(ClubFeeClaim::class, 'sequence', 'organization_id', (int) $run->organization_id);
                $claim = ClubFeeClaim::query()->create([
                    'organization_id' => $run->organization_id,
                    'club_fee_run_id' => $run->id,
                    'club_fee_account_id' => $account->id,
                    'customer_id' => $account->customer_id,
                    'sequence' => $sequence,
                    'number' => $this->number($run->year, $sequence),
                    'kind' => ClubFeeClaim::KIND_CLAIM,
                    'status' => ClubFeeClaimStatus::Open->value,
                    'period_start' => $rows->min('period_start'),
                    'period_end' => $rows->max('period_end'),
                    'issued_on' => $issuedOn->toDateString(),
                    'due_on' => $rows->min('due_on'),
                    'total' => $total,
                    'paid_amount' => Money::zero($currency),
                    'currency' => $currency->value,
                    'payer_snapshot' => $this->payerSnapshot($account),
                ]);
                foreach ($rows as $row) {
                    ClubFeeClaimItem::query()->create([
                        'organization_id' => $run->organization_id,
                        'club_fee_claim_id' => $claim->id,
                        'club_member_id' => $row['member_id'] ?? null,
                        'kind' => (string) $row['kind'],
                        'source_key' => (string) $row['source_key'],
                        'label' => mb_substr((string) $row['label'], 0, 160),
                        'period_start' => $row['period_start'],
                        'period_end' => $row['period_end'],
                        'amount' => Money::of((string) $row['amount'], $currency),
                        'currency' => $currency->value,
                        'basis' => $row['basis'] ?? null,
                    ]);
                }
                $claim->audit('club.fee.claimCreated', ['number' => $claim->number, 'total' => $total->getAmount(), 'run_id' => $run->id]);
                $claims->push($claim);
            }

            $run->update([
                'status' => ClubFeeRunStatus::Released->value,
                'released_at' => now(),
                'released_by_user_id' => $actor->id,
                'claims_count' => $claims->count(),
            ]);
            $run->audit('club.fee.runReleased', ['claims' => $claims->count(), 'skipped' => count($claimed)]);

            return $claims;
        });
    }

    public function cancelRun(ClubFeeRun $run, User $actor): ClubFeeRun {
        $this->assertStatusTransition($run->status, ClubFeeRunStatus::Cancelled);
        $run->update(['status' => ClubFeeRunStatus::Cancelled->value]);
        $run->audit('club.fee.runCancelled', ['actor_id' => $actor->id]);

        return $run->refresh();
    }

    /**
     * Storno einer unbezahlten Forderung: freigegebene Beträge bleiben lesbar,
     * die Quellschlüssel werden freigegeben (Umbenennung), damit ein neuer
     * Lauf die Periode erneut beanspruchen kann.
     */
    public function cancelClaim(ClubFeeClaim $claim, User $actor, string $reason): ClubFeeClaim {
        return DB::transaction(function () use ($claim, $actor, $reason): ClubFeeClaim {
            /** @var ClubFeeClaim $claim */
            $claim = ClubFeeClaim::query()->whereKey($claim->id)->lockForUpdate()->firstOrFail();
            if (! $claim->paid_amount->isZero()) {
                throw ValidationException::withMessages(['reason' => __('club.fees.error.claim_has_payments')]);
            }
            $this->assertStatusTransition($claim->status, ClubFeeClaimStatus::Cancelled);
            foreach ($claim->items()->get() as $item) {
                $item->update(['source_key' => mb_substr('cancelled:' . $claim->id . ':' . $item->source_key, 0, 191)]);
            }
            $claim->update(['status' => ClubFeeClaimStatus::Cancelled->value, 'cancelled_at' => now(), 'cancelled_by_user_id' => $actor->id, 'reason' => trim($reason)]);
            $claim->audit('club.fee.claimCancelled', ['reason' => trim($reason)]);

            return $claim->refresh();
        });
    }

    /**
     * Korrektur als verknüpfte Forderung (positiv = Nachforderung, negativ =
     * Gutschrift); der ursprüngliche Beleg bleibt unverändert.
     */
    public function createCorrection(ClubFeeClaim $claim, User $actor, string $amount, string $label, string $reason): ClubFeeClaim {
        return DB::transaction(function () use ($claim, $actor, $amount, $label, $reason): ClubFeeClaim {
            $money = Money::of($amount, $claim->currency);
            if ($money->isZero()) {
                throw ValidationException::withMessages(['amount' => __('club.fees.error.amount_invalid')]);
            }
            $sequence = $this->nextNo(ClubFeeClaim::class, 'sequence', 'organization_id', (int) $claim->organization_id);
            $account = $claim->account()->with('customer')->firstOrFail();
            $today = CarbonImmutable::today();
            $correction = ClubFeeClaim::query()->create([
                'organization_id' => $claim->organization_id,
                'club_fee_run_id' => null,
                'club_fee_account_id' => $claim->club_fee_account_id,
                'customer_id' => $claim->customer_id,
                'sequence' => $sequence,
                'number' => $this->number((int) $today->year, $sequence),
                'kind' => ClubFeeClaim::KIND_CORRECTION,
                'corrects_claim_id' => $claim->id,
                'status' => ClubFeeClaimStatus::Open->value,
                'period_start' => $claim->period_start->toDateString(),
                'period_end' => $claim->period_end->toDateString(),
                'issued_on' => $today->toDateString(),
                'due_on' => $money->isPositive() ? $today->addDays(14)->toDateString() : $today->toDateString(),
                'total' => $money,
                'paid_amount' => Money::zero($claim->currency),
                'currency' => $claim->currency->value,
                'payer_snapshot' => $this->payerSnapshot($account),
                'reason' => trim($reason),
            ]);
            ClubFeeClaimItem::query()->create([
                'organization_id' => $claim->organization_id,
                'club_fee_claim_id' => $correction->id,
                'club_member_id' => null,
                'kind' => 'correction',
                'source_key' => 'correction:' . $claim->id . ':' . $correction->id,
                'label' => mb_substr(trim($label), 0, 160),
                'period_start' => $claim->period_start->toDateString(),
                'period_end' => $claim->period_end->toDateString(),
                'amount' => $money,
                'currency' => $claim->currency->value,
                'basis' => ['corrects' => $claim->number, 'reason' => trim($reason)],
            ]);
            $correction->audit('club.fee.correctionCreated', ['corrects' => $claim->number, 'amount' => $money->getAmount(), 'reason' => trim($reason), 'actor_id' => $actor->id]);

            return $correction;
        });
    }

    /** Mitteilung per E-Mail mit Zustellnachweis; Versand ist von der Freigabe getrennt. */
    public function sendNotice(ClubFeeClaim $claim, string $email, ?User $actor = null): DocumentDispatch {
        $email = trim($email);
        if (! EmailHelper::isEmail($email)) {
            throw ValidationException::withMessages(['email' => __('club.fees.error.email_required')]);
        }
        $dispatch = DocumentDispatch::query()->create([
            'organization_id' => $claim->organization_id,
            'document_kind' => ClubFeeClaim::DOCUMENT_KIND,
            'document_id' => $claim->id,
            'channel' => DocumentDispatch::CHANNEL_EMAIL,
            'format' => 'pdf',
            'status' => 'queued',
            'recipient' => $email,
            'created_by' => $actor !== null ? $actor->id : Auth::id(),
        ]);
        Mail::to($email)->queue(new \App\Mail\ClubFeeNoticeMail((int) $claim->id, (int) $dispatch->id));
        $claim->audit('club.fee.noticeMailed', ['to' => $email, 'dispatch_id' => $dispatch->id]);

        return $dispatch;
    }

    /** Download-Nachweis der Mitteilung. */
    public function recordDownload(ClubFeeClaim $claim, ?User $actor = null): void {
        DocumentDispatch::query()->create([
            'organization_id' => $claim->organization_id,
            'document_kind' => ClubFeeClaim::DOCUMENT_KIND,
            'document_id' => $claim->id,
            'channel' => DocumentDispatch::CHANNEL_DOWNLOAD,
            'format' => 'pdf',
            'status' => 'sent',
            'created_by' => $actor?->id,
        ]);
    }

    /** Offene Summe je Konto (Forderungen und Korrekturen, ohne Storno). */
    public function openAmountFor(ClubFeeAccount $account): Money {
        $claims = ClubFeeClaim::query()->where('club_fee_account_id', $account->id)->open()->get();
        if ($claims->isEmpty()) {
            return Money::zero($this->fees->defaultCurrency());
        }

        return Money::sum($claims->map(fn(ClubFeeClaim $c): Money => $c->openAmount())->all());
    }

    private function number(int $year, int $sequence): string {
        return sprintf('B%d-%05d', $year, $sequence);
    }

    /** @return array<string, mixed> */
    private function payerSnapshot(ClubFeeAccount $account): array {
        $customer = $account->customer;

        return [
            'name' => $account->name,
            'company' => $customer?->company,
            'street' => $customer?->address_street,
            'zip' => $customer?->address_zip,
            'city' => $customer?->address_city,
            'email' => $account->email ?? $customer?->email,
            'customer_number' => $customer?->number,
        ];
    }
}
