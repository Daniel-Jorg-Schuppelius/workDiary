<?php
/*
 * Created on   : Tue Aug 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ClassificationSuggestionTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Ai;

use App\Enums\Classification\ClassificationDomain;
use App\Enums\User\Permission;
use App\Models\Ai\{AiCapabilitySetting, AiProviderConnection};
use App\Models\{Classification, Customer, Organization, Tag, User};
use App\Services\Ai\Suggestions\ClassificationSuggestionService;
use App\Services\Ai\Support\CustomerNameMasker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\Support\{FakeAiProvider, FakeAiProviderFactory};
use Tests\TestCase;

/**
 * KI-Welle 1 für Klassifikationen (Feature 143, MVP-711): Tag-/Katalogwert-
 * Vorschläge werden auf Bestehendes gemappt (unbekannt → verworfen, nie
 * Tag-Neuanlage), JSON-Endpunkt mit Gating/Tenancy/Budget, Diary-Formular
 * zeigt den Vorschlags-Button nur bei nutzbarer Capability.
 */
class ClassificationSuggestionTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $user;

    private FakeAiProvider $fake;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->fake = FakeAiProviderFactory::install();

        $this->user = User::factory()->user()->create(['organization_id' => $this->organization->id]);
        $this->user->givePermissionTo([Permission::AiUse->value]);

        $connection = AiProviderConnection::factory()->create(['organization_id' => $this->organization->id]);
        AiCapabilitySetting::factory()->create([
            'organization_id' => $this->organization->id,
            'capability' => ClassificationSuggestionService::CAPABILITY,
            'enabled' => true,
            'allowed_connection_ids' => [$connection->id],
        ]);

        foreach (['Wartung', 'Netzwerk', 'Notfall'] as $name) {
            Tag::create(['name' => $name, 'organization_id' => $this->organization->id, 'created_by' => $this->user->id]);
        }
    }

    private function service(): ClassificationSuggestionService {
        return app(ClassificationSuggestionService::class);
    }

    public function test_suggest_tags_maps_to_existing_tags_and_drops_unknown(): void {
        // Katalog-Garantie greift bereits im AiInvocationService (exakte Werte).
        $this->fake->classificationResponse = ['Netzwerk', 'Wartung', 'Erfunden', 'Wartung'];

        $tags = $this->service()->suggestTags($this->organization, 'Switch im Serverraum gewartet, Uplink getauscht');

        $this->assertSame(['Netzwerk', 'Wartung'], $tags->pluck('name')->all());
        $this->assertSame(3, Tag::query()->withoutGlobalScopes()->count(), 'KI darf keine Tags anlegen.');

        $sent = $this->fake->calls[0]['request'];
        $this->assertInstanceOf(\App\Services\Ai\Dto\ClassifyRequest::class, $sent);
        $this->assertTrue($sent->multiple);
        $this->assertEqualsCanonicalizing(['Wartung', 'Netzwerk', 'Notfall'], $sent->catalog);
    }

    public function test_customer_names_are_masked_and_customer_tags_lead_the_catalog(): void {
        $customer = Customer::create([
            'organization_id' => $this->organization->id,
            'name' => 'Musterfirma GmbH',
            'currency' => 'EUR',
            'hourly_rate' => '90.00',
            'created_by' => $this->user->id,
        ]);
        $notfall = Tag::query()->where('name', 'Notfall')->firstOrFail();
        $entry = \App\Models\DiaryEntry::factory()->for($this->user)->create([
            'organization_id' => $this->organization->id,
            'customer_id' => $customer->id,
        ]);
        $entry->tags()->sync([$notfall->id]);
        $this->fake->classificationResponse = ['Notfall'];

        $tags = $this->service()->suggestTags($this->organization, 'Ausfall bei Musterfirma GmbH', $customer);

        $this->assertSame(['Notfall'], $tags->pluck('name')->all());
        $sent = $this->fake->calls[0]['request'];
        $this->assertStringContainsString(CustomerNameMasker::PLACEHOLDER, $sent->text);
        $this->assertStringNotContainsString('Musterfirma', $sent->text);
        $this->assertSame('Notfall', $sent->catalog[0]);
    }

    public function test_suggest_catalog_values_maps_labels_to_classifications(): void {
        Classification::factory()->create([
            'organization_id' => $this->organization->id,
            'domain' => ClassificationDomain::DefectType->value,
            'code' => 'elektrik',
            'label' => 'Elektrik',
        ]);
        Classification::factory()->create([
            'organization_id' => $this->organization->id,
            'domain' => ClassificationDomain::DefectType->value,
            'code' => 'mechanik',
            'label' => 'Mechanik',
        ]);
        $this->fake->classificationResponse = ['Elektrik', 'Sanitär'];

        $values = $this->service()->suggestCatalogValues($this->organization, ClassificationDomain::DefectType, 'Sicherung löst aus');

        $this->assertSame(['elektrik'], $values->pluck('code')->all());
    }

    public function test_empty_text_or_empty_catalog_skips_the_provider(): void {
        $this->assertTrue($this->service()->suggestTags($this->organization, '   ')->isEmpty());
        $this->assertTrue($this->service()->suggestCatalogValues($this->organization, ClassificationDomain::WasteCode, 'x')->isEmpty());
        $this->assertSame(0, $this->fake->callCount());
    }

    public function test_endpoint_returns_sqid_tags_and_catalog_values(): void {
        Classification::factory()->create([
            'organization_id' => $this->organization->id,
            'domain' => ClassificationDomain::DefectType->value,
            'code' => 'elektrik',
            'label' => 'Elektrik',
        ]);
        $this->fake->classificationResponse = ['Wartung', 'Elektrik', 'Fremd'];
        $wartung = Tag::query()->where('name', 'Wartung')->firstOrFail();

        $response = $this->actingAs($this->user)
            ->postJson(route('ai.suggest.tags'), ['text' => 'Sicherung getauscht', 'domain' => ClassificationDomain::DefectType->value])
            ->assertOk();

        $this->assertSame([['id' => $wartung->sqid, 'name' => 'Wartung', 'color' => $wartung->color]], $response->json('tags'));
        $this->assertSame([['code' => 'elektrik', 'label' => 'Elektrik']], $response->json('values'));
        $this->assertSame(2, $this->fake->callCount('classify'));
    }

    public function test_endpoint_rejects_foreign_customer_and_missing_permission(): void {
        $other = Organization::factory()->create();
        $foreign = Customer::create([
            'organization_id' => $other->id,
            'name' => 'Fremd AG',
            'currency' => 'EUR',
            'hourly_rate' => '90.00',
            'created_by' => $this->user->id,
        ]);

        $this->actingAs($this->user)
            ->postJson(route('ai.suggest.tags'), ['text' => 'x', 'customer_id' => $foreign->sqid])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['customer_id']);
        $this->assertSame(0, $this->fake->callCount());

        $stranger = User::factory()->user()->create(['organization_id' => $this->organization->id]);
        $this->actingAs($stranger)
            ->postJson(route('ai.suggest.tags'), ['text' => 'x'])
            ->assertForbidden();
    }

    public function test_endpoint_reports_disabled_capability_and_exhausted_budget_as_422(): void {
        AiCapabilitySetting::query()->update(['enabled' => false]);
        $this->actingAs($this->user)
            ->postJson(route('ai.suggest.tags'), ['text' => 'Serverwartung'])
            ->assertStatus(422)
            ->assertJsonPath('message', __('ai.error.capability_disabled', ['capability' => __('ai.capability_label.classification.tag_suggest')]));

        AiCapabilitySetting::query()->update(['enabled' => true]);
        $this->organization->forceFill([
            'settings' => ['ai' => ['budget' => ['monthly_units' => ['llm' => 1]]]],
        ])->save();
        app()->instance('currentOrganization', $this->organization->fresh());

        // Die actingAs-Instanz hält die Org-Relation aus dem ersten Request —
        // frisch laden, sonst sieht die Middleware die alten Settings.
        $this->user = $this->user->fresh();

        $response = $this->actingAs($this->user)
            ->postJson(route('ai.suggest.tags'), ['text' => 'Serverwartung'])
            ->assertStatus(422);
        $this->assertStringContainsString('Budget', (string) $response->json('message'));
        $this->assertSame(0, $this->fake->callCount());
    }

    public function test_diary_form_shows_suggest_button_only_with_usable_capability(): void {
        // Die URL steht JSON-escaped im data-config des Tag-Pickers.
        $this->actingAs($this->user)->get(route('diary.create'))
            ->assertOk()
            ->assertSee('ai\/suggest\/tags', false)
            ->assertSee(__('ai.suggestion.suggest_tags'));

        AiCapabilitySetting::query()->update(['enabled' => false]);

        $this->actingAs($this->user)->get(route('diary.create'))
            ->assertOk()
            ->assertDontSee('ai\/suggest\/tags', false)
            ->assertDontSee(__('ai.suggestion.suggest_tags'));
    }
}
