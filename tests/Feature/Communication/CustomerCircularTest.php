<?php
/*
 * Created on   : Thu Aug 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CustomerCircularTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Communication;

use App\Enums\Communication\CommunicationVisibility;
use App\Mail\CustomerCircularMail;
use App\Models\Communication\{CustomerCircular, CustomerCircularRecipient};
use App\Models\CommunicationNote;
use App\Models\{Customer, Organization, Project, User};
use App\Services\Communication\CustomerCircularService;
use App\Settings\SettingScope;
use App\Support\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use RuntimeException;
use Tests\TestCase;

/**
 * Kundenrundschreiben (Feature 119, MVP-608).
 *
 * Der Kern ist nicht der Versand, sondern der Nachweis: Wer wurde erreicht,
 * wer nicht — und warum nicht.
 */
class CustomerCircularTest extends TestCase {
    use RefreshDatabase;

    private Organization $org;

    private User $admin;

    protected function setUp(): void {
        parent::setUp();
        $this->org = Organization::factory()->create();
        app()->instance('currentOrganization', $this->org);
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->org->id]);
    }

    /** @param array<string, mixed> $attributes */
    private function customer(array $attributes = []): Customer {
        return Customer::factory()->create(array_merge([
            'organization_id' => $this->org->id,
        ], $attributes));
    }

    /** @param array<string, mixed> $attributes */
    private function circular(array $attributes = []): CustomerCircular {
        return CustomerCircular::query()->create(array_merge([
            'organization_id' => $this->org->id,
            'subject' => 'Preisanpassung 2027',
            'body' => 'Guten Tag :firma, ab dem 01.01. gelten neue Sätze.',
            'created_by' => $this->admin->id,
        ], $attributes));
    }

    private function service(): CustomerCircularService {
        return app(CustomerCircularService::class);
    }

    public function test_audience_respects_bulk_mail_optout(): void {
        $this->customer(['name' => 'Alpha', 'email' => 'a@example.test']);
        $this->customer(['name' => 'Beta', 'email' => 'b@example.test', 'no_bulk_mail' => true]);

        $names = $this->service()->audience([])->pluck('name')->all();

        $this->assertSame(['Alpha'], $names);
    }

    public function test_mandatory_circular_reaches_opted_out_customers(): void {
        $this->customer(['name' => 'Alpha', 'email' => 'a@example.test']);
        $this->customer(['name' => 'Beta', 'email' => 'b@example.test', 'no_bulk_mail' => true]);

        $names = $this->service()->audience([], true)->pluck('name')->all();

        $this->assertSame(['Alpha', 'Beta'], $names);
    }

    public function test_audience_filters_by_zip_prefix_and_city(): void {
        $this->customer(['name' => 'Hannover', 'address_zip' => '30159', 'address_city' => 'Hannover']);
        $this->customer(['name' => 'Berlin', 'address_zip' => '10115', 'address_city' => 'Berlin']);

        $this->assertSame(['Hannover'], $this->service()->audience(['zip_prefix' => '30'])->pluck('name')->all());
        $this->assertSame(['Berlin'], $this->service()->audience(['city' => 'Berlin'])->pluck('name')->all());
    }

    public function test_zip_prefix_wildcard_is_escaped(): void {
        $this->customer(['name' => 'Hannover', 'address_zip' => '30159']);

        // Ein „%" aus der Eingabe darf nicht als Platzhalter wirken.
        $this->assertCount(0, $this->service()->audience(['zip_prefix' => '%']));
    }

    public function test_audience_filters_by_active_project(): void {
        $this->customer(['name' => 'MitProjekt']);
        $without = $this->customer(['name' => 'OhneProjekt']);
        // Die Kunden-Factory legt ein Standardprojekt an — archiviert zählt es nicht.
        Project::query()->where('customer_id', $without->id)->update(['archived_at' => now()]);

        $names = $this->service()->audience(['with_active_projects' => true])->pluck('name')->all();

        $this->assertSame(['MitProjekt'], $names);
    }

    public function test_archived_customers_are_never_addressed(): void {
        $this->customer(['name' => 'Aktiv']);
        $this->customer(['name' => 'Archiviert', 'archived_at' => now()]);

        $this->assertSame(['Aktiv'], $this->service()->audience([])->pluck('name')->all());
    }

    public function test_send_delivers_mail_and_records_recipient(): void {
        Mail::fake();
        $this->customer(['name' => 'Alpha GmbH', 'company' => 'Alpha GmbH', 'email' => 'a@example.test']);
        $circular = $this->circular();

        $this->service()->send($circular, $this->admin);

        Mail::assertSent(CustomerCircularMail::class, 1);
        $recipient = CustomerCircularRecipient::query()->firstOrFail();
        $this->assertSame(CustomerCircularRecipient::STATUS_SENT, $recipient->status);
        $this->assertSame('a@example.test', $recipient->email);
        $this->assertNotNull($recipient->sent_at);
        $this->assertSame(CustomerCircular::STATUS_SENT, $circular->fresh()?->status);
    }

    public function test_customer_without_email_is_recorded_as_skipped(): void {
        Mail::fake();
        $this->customer(['name' => 'Ohne Mail', 'email' => null]);
        $this->customer(['name' => 'Mit Mail', 'email' => 'ok@example.test']);

        $this->service()->send($this->circular(), $this->admin);

        Mail::assertSent(CustomerCircularMail::class, 1);
        $skipped = CustomerCircularRecipient::query()->where('status', CustomerCircularRecipient::STATUS_SKIPPED)->firstOrFail();
        $this->assertSame('no_email', $skipped->reason);
        $this->assertNull($skipped->sent_at);
    }

    public function test_contact_person_email_is_used_as_fallback(): void {
        Mail::fake();
        $this->customer([
            'name' => 'Ohne Hauptmail',
            'email' => null,
            'contact_persons' => [
                ['name' => 'Zweit', 'email' => 'zweit@example.test'],
                ['name' => 'Haupt', 'email' => 'haupt@example.test', 'primary' => true],
            ],
        ]);

        $this->service()->send($this->circular(), $this->admin);

        $this->assertSame('haupt@example.test', CustomerCircularRecipient::query()->firstOrFail()->email);
    }

    public function test_body_placeholders_are_personalized(): void {
        Mail::fake();
        $this->customer(['name' => 'Alpha', 'company' => 'Alpha GmbH', 'email' => 'a@example.test']);

        $this->service()->send($this->circular(), $this->admin);

        Mail::assertSent(CustomerCircularMail::class, fn (CustomerCircularMail $mail): bool => str_contains($mail->body, 'Alpha GmbH')
            && ! str_contains($mail->body, ':firma'));
    }

    public function test_send_writes_communication_note_into_customer_file(): void {
        Mail::fake();
        $customer = $this->customer(['name' => 'Alpha', 'email' => 'a@example.test']);

        $this->service()->send($this->circular(), $this->admin);

        $note = CommunicationNote::query()
            ->where('notable_type', Customer::class)
            ->where('notable_id', $customer->id)
            ->firstOrFail();
        $this->assertSame('Preisanpassung 2027', $note->subject);
        $this->assertSame(CommunicationVisibility::Internal->value, (string) $note->visibility?->value);
    }

    public function test_portal_notice_makes_note_visible_to_customer(): void {
        Mail::fake();
        $customer = $this->customer(['name' => 'Alpha', 'email' => 'a@example.test']);

        $this->service()->send($this->circular(['portal_notice' => true]), $this->admin);

        $note = CommunicationNote::query()->where('notable_id', $customer->id)->firstOrFail();
        $this->assertSame(CommunicationVisibility::Customer->value, (string) $note->visibility?->value);
    }

    public function test_second_send_is_rejected(): void {
        Mail::fake();
        $this->customer(['name' => 'Alpha', 'email' => 'a@example.test']);
        $circular = $this->circular();
        $this->service()->send($circular, $this->admin);

        $this->expectException(RuntimeException::class);
        $this->service()->send($circular->fresh(), $this->admin);
    }

    public function test_send_without_recipients_is_rejected(): void {
        Mail::fake();
        $circular = $this->circular();

        $this->expectException(RuntimeException::class);
        $this->service()->send($circular, $this->admin);

        Mail::assertNothingSent();
    }

    public function test_index_and_show_render(): void {
        Mail::fake();
        $this->customer(['name' => 'Alpha', 'email' => 'a@example.test']);
        $circular = $this->circular();

        $this->actingAs($this->admin)->get(route('circulars.index'))->assertOk()->assertSee('Preisanpassung 2027');
        $this->actingAs($this->admin)->get(route('circulars.show', $circular))->assertOk()->assertSee('Alpha');
        $this->actingAs($this->admin)->get(route('circulars.create'))->assertOk();
    }

    public function test_store_and_send_via_http(): void {
        Mail::fake();
        $this->customer(['name' => 'Alpha', 'email' => 'a@example.test', 'address_zip' => '30159']);
        $this->customer(['name' => 'Beta', 'email' => 'b@example.test', 'address_zip' => '10115']);

        $this->actingAs($this->admin)->post(route('circulars.store'), [
            'subject' => 'Wartungsfenster',
            'body' => 'Am Samstag.',
            'zip_prefix' => '30',
        ])->assertRedirect();

        $circular = CustomerCircular::query()->firstOrFail();
        $this->assertSame(['zip_prefix' => '30'], (array) $circular->filters);

        $this->actingAs($this->admin)->post(route('circulars.send', $circular))->assertRedirect();

        Mail::assertSent(CustomerCircularMail::class, 1);
        $this->assertSame(CustomerCircular::STATUS_SENT, $circular->fresh()?->status);
    }

    public function test_approval_is_off_by_default(): void {
        Mail::fake();
        $this->customer(['name' => 'Alpha', 'email' => 'a@example.test']);

        // Ohne Einstellung geht der Versand wie bisher durch — wer allein
        // arbeitet, hätte sonst eine Sperre ohne Ausweg.
        $this->service()->send($this->circular(), $this->admin);

        Mail::assertSent(CustomerCircularMail::class, 1);
    }

    public function test_approval_required_blocks_the_send(): void {
        Mail::fake();
        Setting::set(CustomerCircularService::APPROVAL_SETTING, true, SettingScope::Organization, $this->org);
        $this->customer(['name' => 'Alpha', 'email' => 'a@example.test']);

        $this->expectException(RuntimeException::class);
        $this->service()->send($this->circular(), $this->admin);
    }

    public function test_the_author_cannot_approve_their_own_circular(): void {
        Setting::set(CustomerCircularService::APPROVAL_SETTING, true, SettingScope::Organization, $this->org);
        $circular = $this->circular();

        $this->expectException(RuntimeException::class);
        $this->service()->approve($circular, $this->admin);
    }

    public function test_a_second_person_approves_and_the_send_goes_through(): void {
        Mail::fake();
        Setting::set(CustomerCircularService::APPROVAL_SETTING, true, SettingScope::Organization, $this->org);
        $this->customer(['name' => 'Alpha', 'email' => 'a@example.test']);
        $second = User::factory()->admin()->create(['organization_id' => $this->org->id]);
        $circular = $this->circular();

        $this->service()->approve($circular, $second);
        $this->assertTrue($circular->fresh()?->isApproved());
        $this->assertSame($second->id, $circular->fresh()?->approved_by);

        $this->service()->send($circular->fresh(), $this->admin);
        Mail::assertSent(CustomerCircularMail::class, 1);
    }

    public function test_approval_via_http(): void {
        Setting::set(CustomerCircularService::APPROVAL_SETTING, true, SettingScope::Organization, $this->org);
        $second = User::factory()->admin()->create(['organization_id' => $this->org->id]);
        $circular = $this->circular();

        $this->actingAs($second)->post(route('circulars.approve', $circular))->assertRedirect();

        $this->assertNotNull($circular->fresh()?->approved_at);
    }

    public function test_customer_form_persists_bulk_mail_optout(): void {
        $customer = $this->customer(['name' => 'Alpha', 'no_bulk_mail' => true]);

        $this->assertTrue((bool) $customer->fresh()?->no_bulk_mail);
        $this->assertCount(0, $this->service()->audience([]));
    }
}
