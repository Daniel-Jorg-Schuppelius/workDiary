<?php
/*
 * Created on   : Mon Sep 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CentralNotesTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Communication;

use App\Enums\Communication\{CommunicationDirection, CommunicationNoteType, CommunicationVisibility};
use App\Enums\Search\SearchSourceType;
use App\Enums\User\Permission;
use App\Models\{CommunicationNote, Customer, Organization, SearchDocument, User};
use App\Services\Communication\CommunicationNoteService;
use App\Services\Search\SearchResultLinker;
use App\Support\{MorphMap, Sqid};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\BuildsPolicyActors;
use Tests\TestCase;

/** Zentrale Notizen und Schnellerfassung (Feature 154, MVP-777). */
class CentralNotesTest extends TestCase {
    use BuildsPolicyActors;
    use RefreshDatabase;

    public function test_internal_note_is_filed_with_the_current_organization_and_ignores_request_ids(): void {
        $user = $this->member();
        $foreign = Organization::factory()->create();

        $this->actingAs($user)
            ->post(route('communication-notes.store'), $this->quickPayload([
                'organization_id' => $foreign->id,
                'notable_type' => MorphMap::alias(Organization::class),
                'notable_id' => $foreign->id,
                'direction' => CommunicationDirection::Outbound->value,
                'visibility' => CommunicationVisibility::Customer->value,
            ]))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $note = CommunicationNote::query()->withoutGlobalScopes()->sole();
        $this->assertTrue($note->isOrganizationNote());
        $this->assertSame((int) $user->organization_id, (int) $note->notable_id);
        $this->assertSame((int) $user->organization_id, (int) $note->organization_id);
        $this->assertSame(CommunicationDirection::Internal, $note->direction);
        $this->assertSame(CommunicationVisibility::Internal, $note->visibility);
    }

    public function test_customer_note_appears_in_the_list_and_the_customer_record_without_customer_visibility(): void {
        $user = $this->member();
        $customer = Customer::factory()->create(['organization_id' => $user->organization_id]);

        $this->actingAs($user)
            ->post(route('communication-notes.store'), $this->quickPayload([
                'storage' => 'customer',
                'customer_id' => Sqid::encode(Customer::class, $customer->id),
                'type' => CommunicationNoteType::Production->value,
                'subject' => 'Kundenwunsch Wartungsfenster',
                'visibility' => CommunicationVisibility::Customer->value,
            ]))
            ->assertSessionHasNoErrors();

        $note = CommunicationNote::query()->withoutGlobalScopes()->sole();
        $this->assertSame(MorphMap::alias(Customer::class), $note->notable_type);
        $this->assertSame($customer->id, (int) $note->notable_id);
        $this->assertSame(CommunicationVisibility::Internal, $note->visibility);

        $this->actingAs($user)->get(route('communication-notes.index'))
            ->assertOk()
            ->assertSee('Kundenwunsch Wartungsfenster');

        $admin = User::factory()->admin()->create(['organization_id' => $user->organization_id]);
        $this->actingAs($admin)->get(route('customers.show', $customer))
            ->assertOk()
            ->assertSee('Kundenwunsch Wartungsfenster');
    }

    public function test_a_call_needs_inbound_or_outbound(): void {
        $user = $this->member();
        $call = ['type' => CommunicationNoteType::Call->value];

        $this->actingAs($user)->post(route('communication-notes.store'), $this->quickPayload($call))
            ->assertSessionHasErrors('direction');
        $this->actingAs($user)->post(route('communication-notes.store'), $this->quickPayload([...$call, 'direction' => CommunicationDirection::Internal->value]))
            ->assertSessionHasErrors('direction');
        $this->assertDatabaseCount('communication_notes', 0);

        $this->actingAs($user)->post(route('communication-notes.store'), $this->quickPayload([...$call, 'direction' => CommunicationDirection::Inbound->value]))
            ->assertRedirect();
        $this->assertSame(CommunicationDirection::Inbound, CommunicationNote::query()->withoutGlobalScopes()->sole()->direction);
    }

    public function test_customer_storage_requires_a_customer_of_the_own_organization(): void {
        $user = $this->member();
        $foreignCustomer = Customer::factory()->create(['organization_id' => Organization::factory()->create()->id]);

        $this->actingAs($user)->post(route('communication-notes.store'), $this->quickPayload(['storage' => 'customer']))
            ->assertSessionHasErrors('customer_id');
        $this->actingAs($user)->post(route('communication-notes.store'), $this->quickPayload([
            'storage' => 'customer',
            'customer_id' => Sqid::encode(Customer::class, $foreignCustomer->id),
        ]))->assertSessionHasErrors('customer_id');

        $this->assertDatabaseCount('communication_notes', 0);
    }

    public function test_organization_notes_can_never_be_shared_with_customers(): void {
        $admin = User::factory()->admin()->create();
        $service = app(CommunicationNoteService::class);

        try {
            $service->create($admin->organization, $admin, $this->serviceAttributes([
                'direction' => CommunicationDirection::Outbound->value,
                'visibility' => CommunicationVisibility::Customer->value,
            ]));
            $this->fail('Eine kundensichtbare Organisationsnotiz wurde angelegt.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('visibility', $e->errors());
        }

        $note = $service->create($admin->organization, $admin, $this->serviceAttributes([
            'direction' => CommunicationDirection::Outbound->value,
        ]));

        $this->actingAs($admin)->get(route('communication-notes.edit', $note))
            ->assertOk()
            ->assertDontSee('name="visibility"', false);

        $this->actingAs($admin)->post(route('communication-notes.publish', $note))
            ->assertSessionHasErrors('visibility');
        $this->assertSame(CommunicationVisibility::Internal, $note->fresh()?->visibility);

        $this->expectException(ValidationException::class);
        $service->update($note, $admin, ['visibility' => CommunicationVisibility::Customer->value]);
    }

    public function test_the_list_shows_only_visible_notes_of_the_own_organization(): void {
        $author = $this->member();
        $organization = $author->organization;
        $colleague = $this->member($organization);
        $admin = User::factory()->admin()->create(['organization_id' => $author->organization_id]);
        $stranger = $this->member();
        $service = app(CommunicationNoteService::class);

        $service->create($organization, $author, $this->serviceAttributes(['subject' => 'Offene Notiz Eins']));
        $service->create($organization, $author, $this->serviceAttributes(['subject' => 'Vertrauliche Notiz Zwei', 'confidential' => true]));
        $service->create($stranger->organization, $stranger, $this->serviceAttributes(['subject' => 'Fremde Notiz Drei']));

        $this->actingAs($colleague)->get(route('communication-notes.index'))
            ->assertOk()
            ->assertSee('Offene Notiz Eins')
            ->assertDontSee('Vertrauliche Notiz Zwei')
            ->assertDontSee('Fremde Notiz Drei');

        $this->actingAs($author)->get(route('communication-notes.index'))
            ->assertOk()
            ->assertSee('Vertrauliche Notiz Zwei')
            ->assertDontSee('Fremde Notiz Drei');

        $this->actingAs($admin)->get(route('communication-notes.index'))
            ->assertOk()
            ->assertSee('Vertrauliche Notiz Zwei')
            ->assertDontSee('Fremde Notiz Drei');
        $this->assertDatabaseHas('audit_logs', ['event' => 'communication.confidential.viewed']);
    }

    public function test_filters_narrow_the_list(): void {
        $user = $this->member();
        $customer = Customer::factory()->create(['organization_id' => $user->organization_id]);
        $service = app(CommunicationNoteService::class);

        $service->create($user->organization, $user, $this->serviceAttributes([
            'subject' => 'Interne Merkzeile',
            'type' => CommunicationNoteType::Production->value,
        ]));
        $service->create($customer, $user, $this->serviceAttributes([
            'subject' => 'Kundentelefonat Angebot',
            'type' => CommunicationNoteType::Call->value,
            'direction' => CommunicationDirection::Inbound->value,
            'next_action' => 'Preise nachreichen',
            'next_action_due_at' => now()->addDay()->toDateTimeString(),
        ]));

        // [Filter, erwartet die interne Notiz?]
        $cases = [
            [['storage' => 'internal'], true],
            [['storage' => 'customer'], false],
            [['customer' => Sqid::encode(Customer::class, $customer->id)], false],
            [['type' => CommunicationNoteType::Production->value], true],
            [['open_followups' => 1], false],
            [['q' => 'Merkzeile'], true],
        ];
        foreach ($cases as [$query, $expectInternal]) {
            $response = $this->actingAs($user)->get(route('communication-notes.index', $query))->assertOk();
            if ($expectInternal) {
                $response->assertSee('Interne Merkzeile')->assertDontSee('Kundentelefonat Angebot');
            } else {
                $response->assertSee('Kundentelefonat Angebot')->assertDontSee('Interne Merkzeile');
            }
        }
    }

    public function test_quick_capture_and_read_dialogs_render(): void {
        $user = $this->member();
        $customer = Customer::factory()->create(['organization_id' => $user->organization_id]);
        $note = app(CommunicationNoteService::class)->create($user->organization, $user, $this->serviceAttributes(['subject' => 'Lesbare Notiz']));

        $this->actingAs($user)->get(route('communication-notes.create', ['customer' => Sqid::encode(Customer::class, $customer->id)]))
            ->assertOk()
            ->assertSee('name="storage"', false)
            ->assertSee('isNone(', false)
            ->assertDontSee('<x-', false);

        $this->actingAs($user)->get(route('communication-notes.show', $note))
            ->assertOk()
            ->assertSee('Lesbare Notiz')
            ->assertDontSee('<x-', false);
    }

    public function test_list_and_capture_require_permissions(): void {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('communication-notes.index'))->assertForbidden();
        $this->actingAs($user)->get(route('communication-notes.create'))->assertForbidden();
        $this->actingAs($user)->post(route('communication-notes.store'), $this->quickPayload())->assertForbidden();
        $this->assertDatabaseCount('communication_notes', 0);
    }

    public function test_search_hits_on_internal_notes_open_the_central_list(): void {
        $user = $this->member();
        $note = app(CommunicationNoteService::class)->create($user->organization, $user, $this->serviceAttributes());

        $document = (new SearchDocument)->forceFill([
            'source_type' => SearchSourceType::CommunicationNote->value,
            'source_id' => $note->id,
        ]);
        $urls = app(SearchResultLinker::class)->urls(collect([$document]));
        $url = route('communication-notes.index', ['note' => Sqid::encode(CommunicationNote::class, (int) $note->id)]);

        $this->assertSame($url, $urls[SearchSourceType::CommunicationNote->value . ':' . $note->id]);
        $this->actingAs($user)->get($url)
            ->assertOk()
            ->assertSee('data-entry-modal-autoopen="note"', false);
    }

    public function test_sidebar_and_create_menu_offer_the_notes(): void {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('href="' . route('communication-notes.index') . '"', false)
            ->assertSee('href="' . route('communication-notes.create') . '"', false);
    }

    public function test_new_types_and_labels_are_translated_in_all_locales(): void {
        foreach (['de', 'en', 'fr', 'it', 'es'] as $locale) {
            app()->setLocale($locale);
            foreach ([CommunicationNoteType::General, CommunicationNoteType::Production] as $type) {
                $this->assertStringNotContainsString('enums.', $type->label(), $locale);
            }
            $this->assertStringNotContainsString('communication.', (string) __('communication.title.notes'), $locale);
            $this->assertStringNotContainsString('communication.', (string) __('communication.error.organization_note_not_publishable'), $locale);
        }
    }

    private function member(?Organization $organization = null): User {
        $user = User::factory()->create($organization instanceof Organization ? ['organization_id' => $organization->id] : []);
        $this->grantPermissions($user, [
            Permission::CommunicationViewAny,
            Permission::CommunicationView,
            Permission::CommunicationCreate,
            Permission::CommunicationUpdate,
        ]);

        return $user;
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function quickPayload(array $overrides = []): array {
        return [
            'storage' => 'internal',
            'type' => CommunicationNoteType::General->value,
            'occurred_at' => now()->subMinutes(30)->format('Y-m-d H:i'),
            'subject' => 'Rückruf Lieferant Stahl',
            'body' => 'Liefertermin verschiebt sich auf Donnerstag.',
            ...$overrides,
        ];
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function serviceAttributes(array $overrides = []): array {
        return [
            'type' => CommunicationNoteType::General->value,
            'direction' => CommunicationDirection::Internal->value,
            'occurred_at' => now()->subHour()->toDateTimeString(),
            'subject' => 'Notiz für den Test',
            'body' => 'Notiztext für den Test.',
            ...$overrides,
        ];
    }
}
