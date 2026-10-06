<?php
/*
 * Created on   : Mon Sep 28 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ClubDonationReceiptTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Club;

use App\Enums\Club\{ClubDonationKind, ClubDonationReceiptKind};
use App\Models\Club\{ClubDonation, ClubDonationReceipt, ClubMember};
use App\Models\Platform\User;
use App\Services\Club\ClubDonationService;
use CommonToolkit\Enums\CurrencyCode;
use CommonToolkit\ValueObjects\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/** MVP-1003: Spenden erfassen, Einzel- und Sammelbestätigung nach amtlichem Muster. */
final class ClubDonationReceiptTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->admin = $this->orgAdmin();
        $this->travelTo('2026-11-15 10:00:00');
    }

    private function saveExemption(bool $fees = false): void {
        $this->actingAs($this->admin)->put(route('club.settings.update'), [
            'donations' => [
                'exemption_kind' => 'exemption_notice', 'tax_office' => 'Musterstadt', 'tax_number' => '12/345/67890',
                'notice_date' => '2025-03-01', 'assessment_period' => '2023', 'purpose' => 'des Sports (§ 52 Abs. 2 Satz 1 Nr. 21 AO)',
                'signatory' => 'Erika Kassenwart', 'membership_fees_confirmable' => $fees ? '1' : '0',
            ],
        ])->assertRedirect();
    }

    public function test_receipts_need_exemption_details_and_freeze_the_donations(): void {
        $member = ClubMember::factory()->create(['first_name' => 'Max', 'last_name' => 'Spender']);
        foreach (['2026-03-01' => '100.00', '2026-09-01' => '50.00'] as $date => $amount) {
            $this->actingAs($this->admin)->post(route('club.fees.donations.store'), [
                'member' => $member->sqid, 'kind' => 'donation', 'amount' => $amount, 'received_on' => $date,
            ])->assertRedirect(route('club.fees.donations.index', ['year' => 2026]));
        }
        $this->actingAs($this->admin)->post(route('club.fees.donations.store'), [
            'donor_name' => 'Anna Gönnerin', 'donor_address' => "Hauptstr. 1\n12345 Musterstadt", 'kind' => 'donation', 'amount' => '101.05', 'received_on' => '2026-05-05', 'is_expense_waiver' => '1',
        ])->assertRedirect();
        $this->actingAs($this->admin)->post(route('club.fees.donations.store'), ['kind' => 'donation', 'amount' => '5', 'received_on' => '2026-05-05'])
            ->assertSessionHasErrors('donor_name');
        $this->actingAs($this->admin)->post(route('club.fees.donations.store'), ['member' => $member->sqid, 'kind' => 'membership_fee', 'amount' => '60', 'received_on' => '2026-05-05'])
            ->assertSessionHasErrors('kind');

        $memberKey = ClubDonation::query()->where('club_member_id', $member->id)->firstOrFail()->donorKey();
        $this->actingAs($this->admin)->post(route('club.fees.donations.collective'), ['donor' => $memberKey, 'year' => 2026])->assertSessionHasErrors('donations');
        $this->assertSame(0, ClubDonationReceipt::query()->count());

        $this->saveExemption();
        $this->actingAs($this->admin)->get(route('club.fees.donations.index', ['year' => 2026]))->assertOk()->assertSee('Max Spender')->assertSee('Anna Gönnerin');
        $this->actingAs($this->admin)->post(route('club.fees.donations.collective'), ['donor' => $memberKey, 'year' => 2026])->assertRedirect();
        $collective = ClubDonationReceipt::query()->sole();
        $this->assertSame(ClubDonationReceiptKind::Collective, $collective->kind);
        $this->assertSame('150.00', $collective->total_amount->getAmount());
        $this->assertSame('ZB-2026-1', $collective->displayNo());
        $this->assertSame('Max Spender', $collective->donor_snapshot['name']);
        $this->assertSame('12/345/67890', $collective->exemption_snapshot['tax_number']);
        $this->actingAs($this->admin)->post(route('club.fees.donations.collective'), ['donor' => $memberKey, 'year' => 2026])->assertSessionHasErrors('donations');

        $receipted = ClubDonation::query()->where('club_member_id', $member->id)->firstOrFail();
        $this->actingAs($this->admin)->put(route('club.fees.donations.update', $receipted), ['member' => $member->sqid, 'kind' => 'donation', 'amount' => '999', 'received_on' => '2026-03-01'])
            ->assertSessionHasErrors('donation');
        $this->assertSame('100.00', $receipted->refresh()->amount->getAmount());

        $single = ClubDonation::query()->whereNull('club_member_id')->sole();
        $this->actingAs($this->admin)->post(route('club.fees.donations.issue', $single))->assertRedirect();
        $receipt = $single->refresh()->receipt;
        $this->assertNotNull($receipt);
        $this->assertSame(2, $receipt->receipt_no);
        $this->assertSame(['Hauptstr. 1', '12345 Musterstadt'], $receipt->donor_snapshot['address']);

        $html = view('club.pdf.donation_receipt', [
            'receipt' => $collective, 'issuer' => ['name' => 'SV Muster', 'lines' => []], 'amountInWords' => app(ClubDonationService::class)->amountInWords($collective->total_amount),
        ])->render();
        $this->assertStringContainsString('Sammelbestätigung über Geldzuwendungen', $html);
        $this->assertStringContainsString('einhundertfünfzig Euro', $html);
        $this->assertStringContainsString('Anlage zur Sammelbestätigung', $html);
        $this->actingAs($this->admin)->get(route('club.fees.donations.receipts.pdf', $receipt))->assertOk()->assertHeader('Content-Type', 'application/pdf');
    }

    /** Sicherheitsaudit 2026-10-04, li-4: ein veralteter Stand stellt keine zweite Bestätigung aus; Bestätigtes ist auf Modellebene unveränderlich. */
    public function test_a_donation_is_receipted_exactly_once_and_frozen_afterwards(): void {
        $this->saveExemption();
        $this->actingAs($this->admin)->post(route('club.fees.donations.store'), [
            'donor_name' => 'Anna Gönnerin', 'donor_address' => "Hauptstr. 1\n12345 Musterstadt", 'kind' => 'donation', 'amount' => '101.05', 'received_on' => '2026-05-05',
        ])->assertRedirect();
        $service = app(ClubDonationService::class);
        $first = ClubDonation::query()->sole();
        $stale = ClubDonation::query()->sole();

        $service->issueSingle($first, $this->admin);
        try {
            $service->issueSingle($stale, $this->admin);
            $this->fail('Zweite Bestätigung hätte abgelehnt werden müssen.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->assertArrayHasKey('donation', $e->errors());
        }
        $this->assertSame(1, ClubDonationReceipt::query()->count());

        $receipted = ClubDonation::query()->sole();
        try {
            $receipted->update(['note' => 'nachträglich']);
            $this->fail('Bestätigte Zuwendung hätte unveränderlich sein müssen.');
        } catch (\RuntimeException) {
        }
        $this->expectException(\RuntimeException::class);
        ClubDonationReceipt::query()->sole()->update(['year' => 2030]);
    }

    /** Die Spendenliste blättert; der Zähler der Karte nennt alle Spenden des Jahres, nicht die der Seite. */
    public function test_the_donation_count_spans_all_pages(): void {
        $service = app(ClubDonationService::class);
        foreach (range(1, 52) as $i) {
            $service->record($this->organization, $this->admin, [
                'club_member_id' => null, 'donor_name' => sprintf('Spenderin %02d', $i), 'donor_address' => 'Hauptstr. 1', 'kind' => ClubDonationKind::Donation,
                'amount' => '10.00', 'received_on' => '2026-03-01', 'is_expense_waiver' => false, 'note' => null,
            ]);
        }

        $first = $this->actingAs($this->admin)->get(route('club.fees.donations.index', ['year' => 2026]))->assertOk();
        $this->assertCount(50, $first->viewData('donations')->items());
        $first->assertSee('>(52)</span>', false)->assertSee('year=2026&amp;page=2', false);

        $second = $this->actingAs($this->admin)->get(route('club.fees.donations.index', ['year' => 2026, 'page' => 2]))->assertOk();
        $this->assertSame(['Spenderin 02', 'Spenderin 01'], collect($second->viewData('donations')->items())->pluck('donor_name')->all());
        $second->assertSee('>(52)</span>', false);
    }

    public function test_membership_fees_only_when_enabled_and_amounts_in_words(): void {
        $this->saveExemption(fees: true);
        $member = ClubMember::factory()->create();
        $this->actingAs($this->admin)->post(route('club.fees.donations.store'), ['member' => $member->sqid, 'kind' => 'membership_fee', 'amount' => '60', 'received_on' => '2026-05-05'])
            ->assertSessionHasNoErrors();

        $words = app(ClubDonationService::class);
        $this->assertSame('einhundertein Euro und fünf Cent', $words->amountInWords(Money::of('101.05', CurrencyCode::Euro)));
        $this->assertSame('ein Euro', $words->amountInWords(Money::of('1.00', CurrencyCode::Euro)));
        $this->assertSame('einundzwanzig Euro und ein Cent', $words->amountInWords(Money::of('21.01', CurrencyCode::Euro)));
    }
}
