<?php
/*
 * Created on   : Mon Sep 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SearchAnswerAiTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Search;

use App\Enums\User\Permission;
use App\Models\Ai\{AiCapabilitySetting, AiProviderConnection, AiTextSuggestion};
use App\Models\{Customer, ForeignCustomer, Project, TimeEntry, User};
use App\Services\Ai\Dto\SummarizeRequest;
use App\Services\Ai\Suggestions\SearchAnswerSuggestionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\Support\{FakeAiProvider, FakeAiProviderFactory};
use Tests\TestCase;

/**
 * Feature 153 (MVP-775): Die KI-Antwort sieht nur die rechtegeprüften Treffer,
 * Kundenbezüge nur als Kennung — und die Antwort gehört dem Suchenden allein.
 */
final class SearchAnswerAiTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private FakeAiProvider $fake;

    private User $admin;

    protected function setUp(): void {
        parent::setUp();
        config(['search.indexing' => true]);
        $this->setUpOrganization(['locale' => 'de']);
        $this->fake = FakeAiProviderFactory::install();

        $this->admin = $this->orgAdmin();
        $this->admin->givePermissionTo(Permission::AiUse->value);

        $local = AiProviderConnection::factory()->local()->create(['organization_id' => $this->organization->id]);
        AiCapabilitySetting::factory()->create([
            'organization_id' => $this->organization->id,
            'capability' => SearchAnswerSuggestionService::CAPABILITY,
            'enabled' => true,
            'allowed_connection_ids' => [$local->id],
        ]);
    }

    public function test_answer_sends_customer_aliases_and_translates_them_back(): void {
        $this->scenario();
        $this->fake->textResponse = 'Am 03.03.2026 wurde bei Kunde A der Sendeconnector auf das SMTP-Relay umgestellt.';

        $this->actingAs($this->admin)
            ->from(route('search.index', ['q' => 'smtp exchange']))
            ->post(route('ai.assist.search-answer'), ['q' => 'smtp exchange'])
            ->assertSessionHas('success');

        $sent = $this->fake->calls[0]['request'];
        $this->assertInstanceOf(SummarizeRequest::class, $sent);
        $prompt = implode("\n", $sent->items);
        $this->assertStringContainsString('Kunde A', $prompt);
        $this->assertStringContainsString('Sendeconnector', $prompt);
        $this->assertStringNotContainsString('Partnerhaus', $prompt);
        $this->assertStringNotContainsString('Sonnenschein', $prompt);

        $suggestion = AiTextSuggestion::query()->withoutGlobalScopes()->firstOrFail();
        $this->assertSame($this->admin->getMorphClass(), $suggestion->subject_type);
        $this->assertStringContainsString('Partnerhaus IT GmbH › Metallbau Sonnenschein', (string) $suggestion->suggestion);

        $this->actingAs($this->admin)
            ->get(route('search.index', ['q' => 'smtp exchange']))
            ->assertOk()
            ->assertSee('Partnerhaus IT GmbH › Metallbau Sonnenschein');
    }

    public function test_only_the_searching_user_may_dismiss_the_answer(): void {
        $this->scenario();
        $this->fake->textResponse = 'Kunde A: Sendeconnector umgestellt.';
        $this->actingAs($this->admin)->post(route('ai.assist.search-answer'), ['q' => 'smtp'])->assertSessionHas('success');
        $suggestion = AiTextSuggestion::query()->withoutGlobalScopes()->firstOrFail();

        $colleague = $this->orgAdmin();
        $colleague->givePermissionTo(Permission::AiUse->value);
        $this->actingAs($colleague)->post(route('ai.assist.reject', $suggestion))->assertNotFound();

        $this->actingAs($this->admin)->post(route('ai.assist.reject', $suggestion))->assertSessionHas('success');
        $this->assertNotSame(AiTextSuggestion::STATUS_PROPOSED, $suggestion->fresh()->status);
    }

    public function test_without_hits_the_provider_is_not_called(): void {
        $this->actingAs($this->admin)
            ->from(route('search.index'))
            ->post(route('ai.assist.search-answer'), ['q' => 'nirgendwovorhanden'])
            ->assertSessionHas('error');

        $this->assertSame(0, $this->fake->callCount());
    }

    private function scenario(): void {
        $partner = Customer::factory()->create(['name' => 'Partnerhaus IT GmbH']);
        $endCustomer = ForeignCustomer::factory()->create([
            'customer_id' => $partner->id,
            'name' => 'Metallbau Sonnenschein',
            'company' => 'Metallbau Sonnenschein',
            'created_by' => $this->admin->id,
        ]);
        $project = Project::factory()->create([
            'customer_id' => $partner->id,
            'foreign_customer_id' => $endCustomer->id,
            'name' => 'Exchange-Migration',
        ]);
        TimeEntry::factory()->create([
            'project_id' => $project->id,
            'user_id' => $this->admin->id,
            'date' => '2026-03-03',
            'minutes' => 90,
            'description' => 'SRV-EX01 (Sendeconnector auf SMTP-Relay umgestellt)',
        ]);
    }
}
