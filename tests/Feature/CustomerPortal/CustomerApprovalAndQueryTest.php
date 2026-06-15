<?php
/*
 * Created on   : Sun Jun 15 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CustomerApprovalAndQueryTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\CustomerPortal;

use App\Enums\Customer\CustomerQueryStatus;
use App\Enums\OpenIssue\{OpenIssueSource, OpenIssueVisibility};
use App\Enums\Protocol\{ProtocolEventType, ProtocolSignatureRole, ProtocolType};
use App\Enums\User\UserRole;
use App\Models\{Customer, CustomerQuery, DiaryEntry, OpenIssue, Protocol, ProtocolSignatureToken, User};
use App\Services\Customer\CustomerQueryService;
use App\Services\Protocol\{ProtocolPdfRenderer, ProtocolService, ProtocolSignatureTokenService};
use Database\Seeders\PermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

class CustomerApprovalAndQueryTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->seed(PermissionsSeeder::class);
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);
        Storage::fake(ProtocolPdfRenderer::DISK);
    }

    public function test_customer_approves_via_token_records_audit(): void {
        [$creator, $protocol] = $this->makeReviewProtocol();
        $token = $this->issueToken($creator, $protocol);

        $response = $this->post(route('protocols.public-sign.submit', ['token' => $token]), [
            'signer_name' => 'Max Kunde',
            'accept' => '1',
        ]);
        $response->assertRedirect();

        $this->assertDatabaseHas('protocol_signatures', [
            'protocol_id' => $protocol->id,
            'signer_name' => 'Max Kunde',
        ]);
        $this->assertDatabaseHas('protocol_signature_tokens', [
            'protocol_id' => $protocol->id,
            'decision' => ProtocolSignatureToken::DECISION_APPROVED,
        ]);
    }

    public function test_customer_rejection_requires_reason(): void {
        [$creator, $protocol] = $this->makeReviewProtocol();
        $token = $this->issueToken($creator, $protocol);

        $response = $this->from(route('protocols.public-sign', ['token' => $token]))
            ->post(route('protocols.public-sign.reject', ['token' => $token]), [
                'signer_name' => 'Max Kunde',
                'reason' => '',
            ]);
        $response->assertSessionHasErrors('reason');

        $this->assertDatabaseCount('open_issues', 0);
        $this->assertDatabaseMissing('protocol_signature_tokens', [
            'decision' => ProtocolSignatureToken::DECISION_REJECTED,
        ]);
    }

    public function test_customer_rejection_creates_open_issues_and_audit(): void {
        [$creator, $protocol] = $this->makeReviewProtocol();
        $token = $this->issueToken($creator, $protocol);

        $response = $this->post(route('protocols.public-sign.reject', ['token' => $token]), [
            'signer_name' => 'Max Kunde',
            'reason' => 'Mängel an der Abnahme.',
            'issues' => ['Fuge am Fenster undicht', 'Lackschaden Tür', ''],
        ]);
        $response->assertRedirect(route('protocols.public-sign', ['token' => $token]));

        // Zwei gemeldete Mängel → zwei kundensichtbare OpenIssues.
        $this->assertSame(2, OpenIssue::query()->count());
        $issue = OpenIssue::query()->first();
        $this->assertNotNull($issue);
        $this->assertSame(OpenIssueSource::CustomerRejection, $issue->source_type);
        $this->assertSame(OpenIssueVisibility::Customer, $issue->visibility);
        $this->assertSame((int) $protocol->id, (int) $issue->source_ref_id);

        $this->assertDatabaseHas('protocol_signature_tokens', [
            'protocol_id' => $protocol->id,
            'decision' => ProtocolSignatureToken::DECISION_REJECTED,
        ]);
        $this->assertDatabaseHas('protocol_events', [
            'protocol_id' => $protocol->id,
            'event' => ProtocolEventType::SignatureRejected,
        ]);
    }

    public function test_customer_query_is_recorded_and_notifies_org(): void {
        \Illuminate\Support\Facades\Notification::fake();
        $leiter = $this->makeTeamLead();
        [$creator, $protocol] = $this->makeReviewProtocol();
        $token = $this->issueToken($creator, $protocol);

        $response = $this->post(route('protocols.public-sign.query', ['token' => $token]), [
            'asker_name' => 'Max Kunde',
            'question' => 'Wann erfolgt die Nacharbeit?',
        ]);
        $response->assertRedirect(route('protocols.public-sign', ['token' => $token]));

        $query = CustomerQuery::query()->first();
        $this->assertNotNull($query);
        $this->assertSame('Wann erfolgt die Nacharbeit?', $query->question);
        $this->assertSame(CustomerQueryStatus::Open, $query->status);

        \Illuminate\Support\Facades\Notification::assertSentTo(
            $leiter,
            \App\Notifications\GenericEventNotification::class,
        );
    }

    public function test_internal_user_can_answer_query_and_customer_sees_it(): void {
        [$creator, $protocol] = $this->makeReviewProtocol();
        $token = $this->issueToken($creator, $protocol);
        $record = app(ProtocolSignatureTokenService::class)->find($token);

        $query = app(CustomerQueryService::class)->raise($protocol, [
            'organization_id' => (int) $protocol->organization_id,
            'signature_token_id' => $record?->id,
            'question' => 'Frage des Kunden?',
        ]);

        $answerer = $this->makeManager();
        $this->actingAs($answerer)
            ->post(route('customer-queries.answer', $query), ['answer' => 'Antwort der Org.'])
            ->assertRedirect(route('customer-queries.index'));

        $query->refresh();
        $this->assertSame(CustomerQueryStatus::Answered, $query->status);
        $this->assertSame('Antwort der Org.', $query->answer);

        // Kunde sieht die Antwort über den Link.
        $this->get(route('protocols.public-sign', ['token' => $token]))
            ->assertOk()
            ->assertSee('Antwort der Org.');
    }

    public function test_query_form_hides_other_customers_and_internal_data(): void {
        [$creatorA, $protocolA] = $this->makeReviewProtocol();
        $tokenA = $this->issueToken($creatorA, $protocolA);

        [$creatorB, $protocolB] = $this->makeReviewProtocol();
        $recordB = app(ProtocolSignatureTokenService::class)->find($this->issueToken($creatorB, $protocolB));

        // Rückfrage am fremden Vorgang B.
        app(CustomerQueryService::class)->raise($protocolB, [
            'organization_id' => (int) $protocolB->organization_id,
            'signature_token_id' => $recordB?->id,
            'question' => 'GEHEIME FREMDE FRAGE',
        ]);

        // Über Token A darf die fremde Rückfrage nicht sichtbar sein.
        $this->get(route('protocols.public-sign', ['token' => $tokenA]))
            ->assertOk()
            ->assertDontSee('GEHEIME FREMDE FRAGE');
    }

    public function test_expired_token_rejection_returns_410(): void {
        [$creator, $protocol] = $this->makeReviewProtocol();
        $token = $this->issueToken($creator, $protocol);
        ProtocolSignatureToken::query()->update(['expires_at' => now()->subDay()]);

        $this->post(route('protocols.public-sign.reject', ['token' => $token]), [
            'reason' => 'Zu spät.',
        ])->assertStatus(410);
    }

    public function test_used_token_rejection_is_refused(): void {
        [$creator, $protocol] = $this->makeReviewProtocol();
        $token = $this->issueToken($creator, $protocol);
        ProtocolSignatureToken::query()->update(['used_at' => now()]);

        $this->post(route('protocols.public-sign.reject', ['token' => $token]), [
            'reason' => 'Bereits benutzt.',
        ])->assertStatus(410);
    }

    public function test_query_management_requires_permission(): void {
        $outsider = User::factory()->user()->create([
            'organization_id' => $this->organization->id,
        ]);

        $this->actingAs($outsider)
            ->get(route('customer-queries.index'))
            ->assertForbidden();
    }

    /**
     * @return array{0: User, 1: Protocol}
     */
    private function makeReviewProtocol(): array {
        $creator = User::factory()->user()->create([
            'organization_id' => $this->organization->id,
        ]);
        $customer = Customer::factory()->create(['organization_id' => $this->organization->id]);
        $entry = DiaryEntry::factory()->for($creator)->create([
            'organization_id' => $this->organization->id,
            'customer_id' => $customer->id,
        ]);
        /** @var ProtocolService $svc */
        $svc = app(ProtocolService::class);
        $p = $svc->create($entry, $creator, [
            'type' => ProtocolType::Acceptance->value,
            'title' => 'Abnahme',
        ]);
        $svc->requestReview($p, $creator);

        return [$creator, $p->refresh()];
    }

    private function issueToken(User $creator, Protocol $protocol): string {
        /** @var ProtocolSignatureTokenService $tokens */
        $tokens = app(ProtocolSignatureTokenService::class);

        return $tokens->issue($protocol, $creator, [
            'role' => ProtocolSignatureRole::Customer->value,
            'signer_email' => 'k@example.org',
        ])['token'];
    }

    private function makeTeamLead(): User {
        $user = User::factory()->create(['organization_id' => $this->organization->id]);
        $user->assignRole(UserRole::Teamleitung->value);

        return $user;
    }

    private function makeManager(): User {
        $user = User::factory()->user()->create(['organization_id' => $this->organization->id]);
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);
        $user->givePermissionTo(\App\Enums\User\Permission::ProtocolCustomerQueryManage->value);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $user;
    }
}
