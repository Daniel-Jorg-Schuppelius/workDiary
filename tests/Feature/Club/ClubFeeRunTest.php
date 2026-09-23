<?php
/*
 * Created on   : Wed Sep 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ClubFeeRunTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Club;

use App\Enums\Club\{ClubFeeClaimStatus, ClubFeeRunStatus};
use App\Enums\User\UserRole;
use App\Mail\ClubFeeNoticeMail;
use App\Models\Club\{ClubFeeAccount, ClubFeeClaim, ClubFeeClaimItem, ClubFeeRun, ClubFeeTariff, ClubMember};
use App\Models\Document\DocumentDispatch;
use App\Models\Platform\User;
use App\Services\Club\{ClubFeeRunService, ClubFeeService, ClubMemberService};
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Beitragslauf und Forderungen (Feature 159, MVP-850): parallele und
 * wiederholte Läufe ohne Doppelbeitrag, Tarifwechsel verändert alte
 * Forderungen nicht, externe Rechnungshoheit blockiert die lokale Freigabe;
 * dazu Fehler-Sperre, Austritt, Storno, Korrektur, Mitteilung und Rechte.
 */
class ClubFeeRunTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;

    private ClubFeeTariff $adults;

    private ClubFeeTariff $family;

    private ClubFeeAccount $account;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->admin = $this->orgAdmin();
        $this->adults = $this->fees()->createTariff($this->organization, ['name' => 'Erwachsene']);
        $this->fees()->saveRate($this->adults, ['valid_from' => '2026-01-01', 'interval' => 'monthly', 'amount' => '30,00', 'anchor_month' => 1, 'due_days' => 14, 'proration' => 'full']);
        $this->family = $this->fees()->createTariff($this->organization, ['name' => 'Familie', 'kind' => 'family']);
        $this->fees()->saveRate($this->family, ['valid_from' => '2026-01-01', 'interval' => 'quarterly', 'amount' => '45,00', 'anchor_month' => 1, 'due_days' => 14, 'proration' => 'full']);
        $this->account = $this->fees()->createAccount($this->organization, ['name' => 'Familie Muster', 'email' => 'muster@example.test'], $this->admin);
    }

    private function fees(): ClubFeeService {
        return app(ClubFeeService::class);
    }

    private function runs(): ClubFeeRunService {
        return app(ClubFeeRunService::class);
    }

    private function member(string $joined = '2026-01-01'): ClubMember {
        return ClubMember::factory()->create(['joined_on' => $joined, 'birth_date' => '1990-05-10']);
    }

    private function assign(ClubMember $member, ClubFeeTariff $tariff, string $from = '2026-01-01', ?ClubFeeAccount $account = null): void {
        $this->fees()->assign($account ?? $this->account, $member, $tariff, ['valid_from' => $from]);
    }

    public function test_repeated_and_parallel_runs_create_each_claim_exactly_once(): void {
        $this->assign($this->member(), $this->adults);
        $this->assign($this->member(), $this->family);
        $this->assign($this->member(), $this->family);
        $other = $this->fees()->createAccount($this->organization, ['name' => 'Einzel'], $this->admin);
        $this->assign($this->member(), $this->adults, '2026-01-01', $other);

        $first = $this->runs()->prepare($this->organization, 2026, 1, $this->admin);
        $second = $this->runs()->prepare($this->organization, 2026, 1, $this->admin);
        $this->assertSame(3, count($first->positions ?? []), 'Einzel + Familie (einmal) + Einzel des zweiten Kontos.');

        $claims = $this->runs()->release($first, $this->admin);
        $this->assertCount(2, $claims, 'Eine Forderung je Konto.');
        $this->assertSame(['B2026-00001', 'B2026-00002'], $claims->pluck('number')->sort()->values()->all());
        $this->assertSame('75.00', $claims->firstWhere('club_fee_account_id', $this->account->id)?->total->getAmount(), '30,00 Einzel + 45,00 Familie.');

        $this->assertCount(0, $this->runs()->release($second, $this->admin), 'Paralleler Lauf desselben Monats erzeugt nichts.');
        $this->assertCount(2, $this->runs()->release($first->refresh(), $this->admin), 'Wiederholte Freigabe liefert die bestehenden Forderungen.');
        $this->assertSame(2, ClubFeeClaim::query()->count());
        $this->assertSame(ClubFeeRunStatus::Released, $second->refresh()->status);
        $this->assertSame(0, $second->claims_count);

        $february = $this->runs()->release($this->runs()->prepare($this->organization, 2026, 2, $this->admin), $this->admin);
        $this->assertCount(2, $february, 'Februar: nur die Monatsbeiträge, kein Familienquartal.');
        $this->assertSame('30.00', $february->firstWhere('club_fee_account_id', $this->account->id)?->total->getAmount());
        $this->assertSame(5, ClubFeeClaimItem::query()->count(), 'Januar: 2 Einzel + 1 Familie; Februar: 2 Einzel.');
    }

    public function test_tariff_change_after_release_does_not_alter_the_claim(): void {
        $member = $this->member();
        $this->assign($member, $this->adults);
        $claim = $this->runs()->release($this->runs()->prepare($this->organization, 2026, 3, $this->admin), $this->admin)->first();
        $this->assertSame('30.00', $claim->total->getAmount());

        $this->fees()->saveRate($this->adults, ['valid_from' => '2026-01-01', 'interval' => 'monthly', 'amount' => '35,00', 'anchor_month' => 1, 'due_days' => 14, 'proration' => 'full'], $this->adults->rates()->firstOrFail());
        $reduced = $this->fees()->createTariff($this->organization, ['name' => 'Ermäßigt']);
        $this->fees()->saveRate($reduced, ['valid_from' => '2026-01-01', 'interval' => 'monthly', 'amount' => '10,00', 'anchor_month' => 1, 'due_days' => 14, 'proration' => 'full']);
        $this->fees()->changeTariff($this->fees()->currentAssignment($member, CarbonImmutable::parse('2026-03-01')), $reduced, CarbonImmutable::parse('2026-04-01'), $this->admin);

        $this->assertSame('30.00', $claim->refresh()->total->getAmount(), 'Freigegebene Forderung bleibt unverändert.');
        $this->assertSame('30.00', $claim->items()->firstOrFail()->amount->getAmount());
        $april = $this->runs()->release($this->runs()->prepare($this->organization, 2026, 4, $this->admin), $this->admin)->first();
        $this->assertSame('10.00', $april->total->getAmount(), 'Neue Periode nutzt den neuen Tarif.');
        $this->assertSame(ClubFeeClaimStatus::Open, $april->status);
    }

    public function test_external_billing_sovereignty_blocks_local_release_but_keeps_preview_and_handover(): void {
        $this->assign($this->member(), $this->adults);
        $this->organization->update(['settings' => ['billing_mode' => 'lexoffice']]);
        app()->instance('currentOrganization', $this->organization->refresh());

        $run = $this->runs()->prepare($this->organization, 2026, 5, $this->admin);
        $this->assertCount(1, $run->positions ?? []);
        try {
            $this->runs()->release($run, $this->admin);
            $this->fail('Externe Rechnungshoheit sperrt die lokale Freigabe.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('status', $e->errors());
        }
        $this->assertSame(0, ClubFeeClaim::query()->count());
        $this->assertSame(ClubFeeRunStatus::Draft, $run->refresh()->status);

        $this->actingAs($this->admin)->get(route('club.fees.runs.show', $run))->assertOk()->assertSee(e(__('club.fees.hint.external_billing', ['mode' => \App\Enums\Finance\BillingMode::Lexoffice->label()])), false)->assertDontSee(route('club.fees.runs.release', $run));
        $export = $this->actingAs($this->admin)->get(route('club.fees.runs.export', $run));
        $export->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString('Familie Muster', $export->streamedContent());
        $this->actingAs($this->admin)->post(route('club.fees.runs.release', $run))->assertSessionHasErrors('status');
    }

    public function test_preview_issues_block_release_and_leaving_ends_future_claims(): void {
        $member = $this->member();
        $empty = $this->fees()->createTariff($this->organization, ['name' => 'Ohne Satz']);
        $this->assign($member, $empty);
        $run = $this->runs()->prepare($this->organization, 2026, 6, $this->admin);
        $this->assertNotEmpty($run->issues);
        try {
            $this->runs()->release($run, $this->admin);
            $this->fail('Fehler in der Vorschau sperren die Freigabe.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('status', $e->errors());
        }

        $this->fees()->saveRate($empty, ['valid_from' => '2026-01-01', 'interval' => 'monthly', 'amount' => '20,00', 'anchor_month' => 1, 'due_days' => 14, 'proration' => 'full']);
        $run = $this->runs()->prepare($this->organization, 2026, 6, $this->admin, $run);
        $this->assertSame([], $run->issues);
        $this->assertCount(1, $this->runs()->release($run, $this->admin));

        app(ClubMemberService::class)->leave($member, CarbonImmutable::parse('2026-06-30'), $this->admin);
        $july = $this->runs()->prepare($this->organization, 2026, 7, $this->admin);
        $this->assertSame([], $july->positions, 'Austritt beendet künftige Beiträge.');
        $this->assertSame(1, ClubFeeClaim::query()->count(), 'Bestehende Forderung bleibt.');
    }

    public function test_notice_pdf_and_mail_dispatch_cancellation_and_correction(): void {
        Mail::fake();
        $member = $this->member();
        $this->assign($member, $this->adults);
        $claim = $this->runs()->release($this->runs()->prepare($this->organization, 2026, 8, $this->admin), $this->admin)->first();

        $pdf = $this->actingAs($this->admin)->get(route('club.fees.claims.pdf', $claim));
        $pdf->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', (string) $pdf->getContent());
        $this->assertSame(1, DocumentDispatch::query()->where('document_kind', 'fee_notice')->where('channel', DocumentDispatch::CHANNEL_DOWNLOAD)->count());

        $this->actingAs($this->admin)->post(route('club.fees.claims.send', $claim), ['email' => 'muster@example.test'])->assertRedirect(route('club.fees.claims.show', $claim));
        Mail::assertQueued(ClubFeeNoticeMail::class, fn(ClubFeeNoticeMail $mail): bool => $mail->claimId === $claim->id);
        $dispatch = DocumentDispatch::query()->where('document_kind', 'fee_notice')->where('channel', DocumentDispatch::CHANNEL_EMAIL)->firstOrFail();
        $this->assertSame('queued', $dispatch->status);
        $this->assertSame('muster@example.test', $dispatch->recipient);
        $this->actingAs($this->admin)->get(route('club.fees.claims.show', $claim))->assertOk()->assertSee('muster@example.test')->assertSee($claim->number);

        $correction = $this->runs()->createCorrection($claim, $this->admin, '-10.00', 'Gutschrift Sommerpause', 'Halle geschlossen');
        $this->assertSame('-10.00', $correction->total->getAmount());
        $this->assertSame($claim->id, $correction->corrects_claim_id);
        $this->assertSame('30.00', $claim->refresh()->total->getAmount(), 'Original bleibt unverändert.');
        $this->assertSame('20.00', $this->runs()->openAmountFor($this->account)->getAmount());

        $this->actingAs($this->admin)->post(route('club.fees.claims.cancel', $claim), ['reason' => 'Doppelt erfasst'])->assertRedirect();
        $this->assertSame(ClubFeeClaimStatus::Cancelled, $claim->refresh()->status);
        $this->assertStringStartsWith('cancelled:', $claim->items()->firstOrFail()->source_key, 'Quellschlüssel wird freigegeben.');
        $again = $this->runs()->release($this->runs()->prepare($this->organization, 2026, 8, $this->admin), $this->admin);
        $this->assertCount(1, $again, 'Nach Storno kann ein neuer Lauf die Periode erneut beanspruchen.');
        $this->assertSame('B2026-00003', $again->first()->number);
    }

    public function test_pages_and_rights(): void {
        $treasurer = $this->userWithRole(UserRole::Buchhaltung->value);
        $lead = $this->userWithRole(UserRole::Teamleitung->value);
        $this->assign($this->member(), $this->adults);
        $run = $this->runs()->prepare($this->organization, 2026, 9, $treasurer);

        $this->actingAs($treasurer)->get(route('club.fees.runs.index'))->assertOk()->assertSee('September 2026');
        $this->actingAs($treasurer)->get(route('club.fees.runs.create'))->assertOk();
        $this->actingAs($treasurer)->post(route('club.fees.runs.store'), ['month' => '2026-10'])->assertRedirect();
        $this->assertSame(2, ClubFeeRun::query()->count());
        $this->actingAs($treasurer)->post(route('club.fees.runs.release', $run))->assertRedirect(route('club.fees.runs.show', $run));
        $claim = ClubFeeClaim::query()->firstOrFail();
        $this->actingAs($treasurer)->get(route('club.fees.runs.show', $run))->assertOk()->assertSee($claim->number);
        $this->actingAs($treasurer)->get(route('club.fees.claims.index'))->assertOk()->assertSee($claim->number);
        $this->actingAs($treasurer)->get(route('club.fees.claims.index', ['status' => 'overdue']))->assertOk();
        $this->actingAs($treasurer)->get(route('club.fees.claims.show', $claim))->assertOk();
        $this->actingAs($treasurer)->get(route('club.fees.claims.send.edit', $claim))->assertOk()->assertSee('muster@example.test');
        $this->actingAs($treasurer)->get(route('club.fees.claims.cancel.edit', $claim))->assertOk();
        $this->actingAs($treasurer)->get(route('club.fees.claims.correction.create', $claim))->assertOk();
        $this->actingAs($treasurer)->get(route('club.fees.accounts.show', $this->account))->assertOk()->assertSee($claim->number);
        $this->actingAs($this->admin)->get(route('club.settings.edit'))->assertOk()->assertSee('name="fee_notice_footer"', false);
        $this->actingAs($this->admin)->put(route('club.settings.update'), ['graduation_enabled' => 0, 'fee_notice_footer' => 'Steuerlich: Mitgliedsbeiträge'])->assertRedirect();
        $this->assertSame('Steuerlich: Mitgliedsbeiträge', data_get($this->organization->refresh()->settings, 'club.fees.notice_footer'));

        $this->actingAs($lead)->get(route('club.fees.runs.index'))->assertForbidden();
        $this->actingAs($lead)->get(route('club.fees.claims.show', $claim))->assertForbidden();
        $this->actingAs($this->orgUser())->get(route('club.fees.claims.pdf', $claim))->assertForbidden();
    }
}
