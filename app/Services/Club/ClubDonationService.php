<?php
/*
 * Created on   : Mon Sep 28 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ClubDonationService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Club;

use App\Enums\Club\{ClubDonationKind, ClubDonationReceiptKind};
use App\Models\Club\{ClubDonation, ClubDonationReceipt, ClubMember};
use App\Models\Platform\{Organization, User};
use App\Services\Concerns\AssignsSequentialNo;
use CommonToolkit\Enums\CurrencyCode;
use CommonToolkit\Helper\Data\NumberHelper;
use CommonToolkit\ValueObjects\Money;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Spenden und Zuwendungsbestätigungen (Feature 159, MVP-1003). Die App prüft
 * nicht, ob der Verein steuerbegünstigt ist: Sie druckt die hinterlegten
 * Freistellungsdaten und verweigert die Bestätigung ohne sie.
 */
class ClubDonationService {
    use AssignsSequentialNo;

    /** Freistellung durch Bescheid bzw. Anlage zum KSt-Bescheid oder Feststellung nach § 60a AO. */
    public const EXEMPTION_KINDS = ['exemption_notice', 'assessment_60a'];

    /** @return array{tax_office: string, tax_number: string, exemption_kind: string, notice_date: string, assessment_period: string, purpose: string, signatory: string} */
    public function exemption(Organization $organization): array {
        $stored = (array) data_get($organization->settings, 'club.donations', []);
        $value = static fn (string $key): string => trim((string) ($stored[$key] ?? ''));

        return [
            'tax_office' => $value('tax_office'),
            'tax_number' => $value('tax_number'),
            'exemption_kind' => in_array($value('exemption_kind'), self::EXEMPTION_KINDS, true) ? $value('exemption_kind') : '',
            'notice_date' => $value('notice_date'),
            'assessment_period' => $value('assessment_period'),
            'purpose' => $value('purpose'),
            'signatory' => $value('signatory'),
        ];
    }

    /** @param array<string, string> $exemption */
    public function exemptionComplete(array $exemption): bool {
        foreach (['tax_office', 'tax_number', 'exemption_kind', 'notice_date', 'purpose'] as $key) {
            if (($exemption[$key] ?? '') === '') {
                return false;
            }
        }

        return $exemption['exemption_kind'] !== 'exemption_notice' || ($exemption['assessment_period'] ?? '') !== '';
    }

    public function membershipFeesConfirmable(Organization $organization): bool {
        return filter_var(data_get($organization->settings, 'club.donations.membership_fees_confirmable', false), FILTER_VALIDATE_BOOL);
    }

    /** @param array{club_member_id: ?int, donor_name: ?string, donor_address: ?string, kind: ClubDonationKind, amount: string, received_on: string, is_expense_waiver: bool, note: ?string} $data */
    public function record(Organization $organization, User $actor, array $data): ClubDonation {
        $this->assertValid($organization, $data);

        return ClubDonation::query()->create($data + ['organization_id' => $organization->id, 'currency' => CurrencyCode::Euro, 'created_by' => $actor->id]);
    }

    /** @param array{club_member_id: ?int, donor_name: ?string, donor_address: ?string, kind: ClubDonationKind, amount: string, received_on: string, is_expense_waiver: bool, note: ?string} $data */
    public function update(ClubDonation $donation, array $data): ClubDonation {
        $this->assertOpen($donation);
        $organization = $donation->organization()->firstOrFail();
        $this->assertValid($organization, $data);
        $donation->update($data);

        return $donation;
    }

    public function delete(ClubDonation $donation): void {
        $this->assertOpen($donation);
        $donation->delete();
    }

    public function issueSingle(ClubDonation $donation, User $actor): ClubDonationReceipt {
        $this->assertOpen($donation);

        return $this->issue($donation->organization()->firstOrFail(), collect([$donation]), ClubDonationReceiptKind::Single, $actor);
    }

    /** Sammelbestätigung: alle offenen Zuwendungen desselben Zuwendenden im Kalenderjahr. */
    public function issueCollective(Organization $organization, string $donorKey, int $year, User $actor): ClubDonationReceipt {
        $donations = ClubDonation::query()->where('organization_id', $organization->id)->whereNull('club_donation_receipt_id')
            ->whereYear('received_on', $year)->with('member')->orderBy('received_on')->get()
            ->filter(static fn (ClubDonation $donation): bool => $donation->donorKey() === $donorKey)->values();
        if ($donations->isEmpty()) {
            throw ValidationException::withMessages(['donations' => (string) __('club.donations.error.nothing_open')]);
        }

        return $this->issue($organization, $donations, ClubDonationReceiptKind::Collective, $actor);
    }

    /**
     * Offene Zuwendungen eines Jahres je Zuwendendem (für die Sammelbestätigung).
     *
     * @return Collection<array-key, Collection<int, ClubDonation>>
     */
    public function openByDonor(Organization $organization, int $year): Collection {
        return ClubDonation::query()->where('organization_id', $organization->id)->whereNull('club_donation_receipt_id')
            ->whereYear('received_on', $year)->with('member')->orderBy('received_on')->get()->toBase()
            ->groupBy(static fn (ClubDonation $donation): string => $donation->donorKey());
    }

    /** Betrag in Buchstaben, wie ihn das amtliche Muster verlangt: „einhundertein Euro und fünf Cent“. */
    public function amountInWords(Money $amount): string {
        $cents = $amount->getMinorAmount();
        $euros = intdiv($cents, 100);
        $rest = $cents % 100;
        // „eins“ steht nur allein; vor der Einheit heißt es „ein“ (einhundertein Euro).
        $words = preg_replace('/eins$/u', 'ein', NumberHelper::toWords($euros)) . ' Euro';

        return $rest === 0 ? $words : $words . ' und ' . preg_replace('/eins$/u', 'ein', NumberHelper::toWords($rest)) . ' Cent';
    }

    /** @param Collection<int, ClubDonation> $donations */
    private function issue(Organization $organization, Collection $donations, ClubDonationReceiptKind $kind, User $actor): ClubDonationReceipt {
        $exemption = $this->exemption($organization);
        if (! $this->exemptionComplete($exemption)) {
            throw ValidationException::withMessages(['donations' => (string) __('club.donations.error.exemption_missing')]);
        }
        if ($donations->map(static fn (ClubDonation $donation): int => (int) $donation->received_on->year)->unique()->count() !== 1) {
            throw ValidationException::withMessages(['donations' => (string) __('club.donations.error.one_year')]);
        }
        $first = $donations->firstOrFail();
        $member = $first->member;

        return DB::transaction(function () use ($organization, $donations, $kind, $actor, $exemption, $first, $member): ClubDonationReceipt {
            // Zeilen sperren und den Stand neu lesen: zwei gleichzeitige Aufrufe stellten sonst zwei Bestätigungen
            // für dieselbe Zuwendung aus (Sicherheitsaudit 2026-10-04, li-4).
            $locked = ClubDonation::query()->whereKey($donations->map(static fn (ClubDonation $donation): int => $donation->id)->all())->lockForUpdate()->get();
            if ($locked->count() !== $donations->count() || $locked->contains(static fn (ClubDonation $donation): bool => $donation->isReceipted())) {
                throw ValidationException::withMessages(['donation' => (string) __('club.donations.error.receipted')]);
            }

            $receipt = ClubDonationReceipt::query()->create([
                'organization_id' => $organization->id,
                'receipt_no' => $this->nextNo(ClubDonationReceipt::class, 'receipt_no', 'organization_id', (int) $organization->id),
                'year' => (int) $first->received_on->year,
                'kind' => $kind,
                'club_member_id' => $member?->id,
                'donor_snapshot' => [
                    'name' => $first->donorLabel(),
                    'address' => $member instanceof ClubMember ? $member->postalAddressLines() : array_values(array_filter(array_map('trim', preg_split('/\R/u', (string) $first->donor_address) ?: []))),
                ],
                'exemption_snapshot' => $exemption,
                'total_amount' => Money::sum($locked->map(static fn (ClubDonation $donation): Money => $donation->amount)->all(), CurrencyCode::Euro),
                'currency' => CurrencyCode::Euro,
                'issued_on' => now()->toDateString(),
                'created_by' => $actor->id,
            ]);
            foreach ($locked as $donation) {
                $donation->update(['club_donation_receipt_id' => $receipt->id]);
            }

            return $receipt;
        });
    }

    private function assertOpen(ClubDonation $donation): void {
        if ($donation->isReceipted()) {
            throw ValidationException::withMessages(['donation' => (string) __('club.donations.error.receipted')]);
        }
    }

    /** @param array<string, mixed> $data */
    private function assertValid(Organization $organization, array $data): void {
        if (($data['club_member_id'] ?? null) === null && trim((string) ($data['donor_name'] ?? '')) === '') {
            throw ValidationException::withMessages(['donor_name' => (string) __('club.donations.error.donor_missing')]);
        }
        if (($data['kind'] ?? null) === ClubDonationKind::MembershipFee && ! $this->membershipFeesConfirmable($organization)) {
            throw ValidationException::withMessages(['kind' => (string) __('club.donations.error.fees_not_confirmable')]);
        }
    }
}
