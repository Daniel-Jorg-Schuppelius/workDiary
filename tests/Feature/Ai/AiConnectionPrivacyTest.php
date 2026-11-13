<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AiConnectionPrivacyTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Ai;

use App\Enums\Ai\{AiApprovalState, AiFamily, AiProviderType};
use App\Models\Ai\{AiCapabilitySetting, AiProviderConnection};
use App\Models\Audit\AuditLog;
use App\Models\Platform\Organization;
use App\Services\Ai\{AiConnectionPrivacy, AiInvocationService};
use App\Services\Ai\Dto\{AiExtractionResult, AiTextResult, AiTranslationResult, ExtractRequest, FormulateRequest, TranslateRequest};
use App\Services\Ai\Exceptions\AiUnavailableException;
use App\Settings\{SettingScope, SettingsRegistry};
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\{RegistersAiCapabilities, WithOrganization};
use Tests\Support\{FakeAiProvider, FakeAiProviderFactory};
use Tests\TestCase;

/**
 * Datenschutz der KI-Verbindungen (Feature 025, MVP-1671/1672):
 * Maskierung je Verbindung mit Rückersetzung, Freigabepflicht im Routing,
 * Frist der Freigabe und Vorgaben für neue und bestehende Organisationen.
 * Alle Werte sind erfunden (prüfziffergültig, siehe SensitiveDataMaskTest).
 */
class AiConnectionPrivacyTest extends TestCase {
    use RefreshDatabase;
    use RegistersAiCapabilities;
    use WithOrganization;

    private const string FORMULATE = 'test.formulate';

    private const string TRANSLATE = 'test.translate';

    private const string EXTRACT = 'test.extract';

    private const string IBAN_SPACED = 'DE19 1203 0000 1001 2345 67';

    private const string IBAN_SECOND = 'DE85760260004433221100';

    private const string MIGRATION = '2027_04_18_500100_keep_ai_connection_approval_optional_for_existing_organizations.php';

    private FakeAiProvider $fake;

    protected function setUp(): void {
        parent::setUp();
        CarbonImmutable::setTestNow('2026-11-12 10:00:00');
        $this->setUpOrganization();
        $this->fake = FakeAiProviderFactory::install();

        $this->registerAiCapability(self::FORMULATE);
        $this->registerAiCapability(self::TRANSLATE, ['verb' => 'translate']);
        $this->registerAiCapability(self::EXTRACT, ['verb' => 'extract']);
    }

    protected function tearDown(): void {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    private function privacy(): AiConnectionPrivacy {
        return app(AiConnectionPrivacy::class);
    }

    /** @param array<string, mixed> $attributes */
    private function connection(array $attributes = []): AiProviderConnection {
        return AiProviderConnection::factory()->create(array_merge(['organization_id' => $this->organization->id], $attributes));
    }

    private function allow(string $capability, AiProviderConnection ...$connections): void {
        AiCapabilitySetting::factory()->create([
            'organization_id' => $this->organization->id,
            'capability' => $capability,
            'enabled' => true,
            'allowed_connection_ids' => array_map(static fn (AiProviderConnection $c): int => (int) $c->id, $connections),
            'default_connection_id' => (int) $connections[0]->id,
        ]);
    }

    private function setting(string $key, mixed $value): void {
        app(SettingsRegistry::class)->set($key, $value, SettingScope::Organization, $this->organization);
        $this->organization->refresh();
    }

    public function test_new_organizations_require_approval_and_the_migration_keeps_existing_ones_optional(): void {
        $existing = Organization::factory()->create(['settings' => ['ai' => ['budget' => ['monthly_units' => ['llm' => 5000]]]]]);
        $alreadyDecided = Organization::factory()->create(['settings' => ['ai' => ['privacy' => ['require_approval' => true]]]]);
        $this->assertTrue($this->privacy()->requiresApproval($existing), 'Vorgabe ab Werk: an.');

        (require database_path('migrations/' . self::MIGRATION))->up();

        $this->assertFalse($this->privacy()->requiresApproval($existing->refresh()));
        $this->assertSame(5000, data_get($existing->settings, 'ai.budget.monthly_units.llm'), 'Andere Einstellungen bleiben.');
        $this->assertTrue($this->privacy()->requiresApproval($alreadyDecided->refresh()), 'Eine gesetzte Einstellung bleibt.');

        $new = Organization::factory()->create();
        $this->assertTrue($this->privacy()->requiresApproval($new));
        $this->assertSame(12, $this->privacy()->approvalMonths($new));
    }

    public function test_unapproved_connection_is_not_used_and_the_message_names_the_reason(): void {
        $connection = AiProviderConnection::factory()->unapproved()->create(['organization_id' => $this->organization->id, 'name' => 'Cloud ohne AVV']);
        $this->allow(self::FORMULATE, $connection);

        try {
            app(AiInvocationService::class)->invoke($this->organization, self::FORMULATE, new FormulateRequest('Wartung'));
            $this->fail('Eine nicht freigegebene Verbindung wurde genutzt.');
        } catch (AiUnavailableException $e) {
            $this->assertSame(AiUnavailableException::REASON_NO_CONNECTION, $e->reason);
            $this->assertStringContainsString('Cloud ohne AVV', $e->getMessage());
            $this->assertStringContainsString((string) __('ai.privacy.reason.missing'), $e->getMessage());
        }
        $this->assertSame(0, $this->fake->callCount());
    }

    public function test_unapproved_connection_is_used_while_the_organization_does_not_require_approval(): void {
        $this->setting(AiConnectionPrivacy::SETTING_REQUIRE_APPROVAL, false);
        $connection = AiProviderConnection::factory()->unapproved()->create(['organization_id' => $this->organization->id]);
        $this->allow(self::FORMULATE, $connection);

        $result = app(AiInvocationService::class)->invoke($this->organization, self::FORMULATE, new FormulateRequest('Wartung'));

        $this->assertSame((int) $connection->id, $result->connectionId);
        $this->assertSame(AiApprovalState::Missing, $this->privacy()->state($connection, $this->organization));
    }

    public function test_expired_approval_falls_back_to_the_approved_connection(): void {
        $expired = $this->connection(['name' => 'Abgelaufen', 'privacy_reviewed_at' => CarbonImmutable::now()->subMonths(13)]);
        $approved = $this->connection(['name' => 'Freigegeben']);
        $this->allow(self::FORMULATE, $expired, $approved);

        $result = app(AiInvocationService::class)->invoke($this->organization, self::FORMULATE, new FormulateRequest('Wartung'));

        $this->assertSame((int) $approved->id, $result->connectionId);
        $this->assertSame(AiApprovalState::Expired, $this->privacy()->state($expired, $this->organization));
        $this->assertStringContainsString('12.10.2026', (string) $this->privacy()->blockingReason($expired, $this->organization));
    }

    public function test_approval_validity_follows_the_organization_setting(): void {
        $connection = $this->connection(['privacy_reviewed_at' => CarbonImmutable::now()->subMonths(5)]);

        $this->assertSame(AiApprovalState::Approved, $this->privacy()->state($connection, $this->organization));

        $this->setting(AiConnectionPrivacy::SETTING_APPROVAL_MONTHS, 3);
        $this->assertSame(AiApprovalState::Expired, $this->privacy()->state($connection, $this->organization));

        $this->setting(AiConnectionPrivacy::SETTING_APPROVAL_MONTHS, 6);
        $this->assertSame(AiApprovalState::Expiring, $this->privacy()->state($connection, $this->organization), 'Ablauf in einem Monat: Erinnerung.');
        $this->assertSame('2026-12-12', $this->privacy()->expiresAt($connection, $this->organization)?->toDateString());
    }

    public function test_incomplete_details_never_count_as_approved(): void {
        $cloud = $this->connection(['has_dpa' => false]);
        $local = $this->connection(['is_local' => true, 'provider' => AiProviderType::OpenAiCompatible, 'has_dpa' => false]);
        $noEu = $this->connection(['is_eu_processing' => false]);

        $this->assertSame(AiApprovalState::Missing, $this->privacy()->state($cloud, $this->organization));
        $this->assertSame(AiApprovalState::Approved, $this->privacy()->state($local, $this->organization), 'Lokal ohne Auftragsverarbeiter.');
        $this->assertSame(AiApprovalState::Missing, $this->privacy()->state($noEu, $this->organization));
    }

    public function test_cloud_request_is_masked_and_the_answer_restored_without_plain_text_in_audit_or_cache(): void {
        $connection = $this->connection();
        $this->allow(self::FORMULATE, $connection);
        $this->fake->textResponse = 'Bitte überweisen Sie auf [IBAN-1] und antworten Sie an [EMAIL-1].';

        $result = app(AiInvocationService::class)->invoke($this->organization, self::FORMULATE, new FormulateRequest(
            'zahlung auf ' . self::IBAN_SPACED . ' rückfrage anna@example.org',
        ));

        $sent = $this->fake->calls[0]['request'];
        $this->assertInstanceOf(FormulateRequest::class, $sent);
        $this->assertSame('zahlung auf [IBAN-1] rückfrage [EMAIL-1]', $sent->text);
        $this->assertInstanceOf(AiTextResult::class, $result->result);
        $this->assertSame('Bitte überweisen Sie auf ' . self::IBAN_SPACED . ' und antworten Sie an anna@example.org.', $result->result->text);

        $audit = AuditLog::query()->where('event', 'ai.invoked')->latest('id')->firstOrFail();
        $this->assertSame(['email' => 1, 'iban' => 1], data_get($audit->changes, 'masked'));
        $this->assertStringNotContainsString('anna@example.org', (string) json_encode($audit->changes));
        $this->assertStringNotContainsString('1203', (string) json_encode($audit->changes));

        // Gleiche Anfrage mit anderen Werten: der Cache liefert die maskierte Antwort, eingesetzt werden die eigenen Werte.
        $second = app(AiInvocationService::class)->invoke($this->organization, self::FORMULATE, new FormulateRequest(
            'zahlung auf ' . self::IBAN_SECOND . ' rückfrage bert@example.net',
        ));
        $this->assertTrue($second->fromCache);
        $this->assertSame(1, $this->fake->callCount());
        $this->assertInstanceOf(AiTextResult::class, $second->result);
        $this->assertSame('Bitte überweisen Sie auf ' . self::IBAN_SECOND . ' und antworten Sie an bert@example.net.', $second->result->text);
    }

    public function test_local_connection_sees_plain_text_unless_the_organization_masks_local_connections(): void {
        $local = $this->connection(['is_local' => true, 'provider' => AiProviderType::Ollama]);
        $this->allow(self::FORMULATE, $local);

        app(AiInvocationService::class)->invoke($this->organization, self::FORMULATE, new FormulateRequest('Rückruf anna@example.org'));
        $this->setting(AiConnectionPrivacy::SETTING_MASK_LOCAL, true);
        app(AiInvocationService::class)->invoke($this->organization, self::FORMULATE, new FormulateRequest('Rückruf bert@example.net'));

        $this->assertSame('Rückruf anna@example.org', $this->fake->calls[0]['request']->text ?? null);
        $this->assertSame('Rückruf [EMAIL-1]', $this->fake->calls[1]['request']->text ?? null);
        $masked = AuditLog::query()->where('event', 'ai.invoked')->orderBy('id')->get()
            ->map(static fn (AuditLog $audit): mixed => data_get($audit->changes, 'masked'))->all();
        $this->assertSame([null, ['email' => 1]], $masked, 'Lokal ohne Einstellung: nicht angewandt.');
    }

    public function test_translation_and_extraction_get_the_values_back(): void {
        $translation = $this->connection(['family' => AiFamily::Translation]);
        $llm = $this->connection();
        $this->allow(self::TRANSLATE, $translation);
        $this->allow(self::EXTRACT, $llm);

        $this->fake->textResponse = 'Please transfer to [IBAN-1] (VAT ID [VATID-1]).';
        $translated = app(AiInvocationService::class)->invoke($this->organization, self::TRANSLATE, new TranslateRequest(
            'Bitte auf ' . self::IBAN_SECOND . ' überweisen (USt-IdNr. DE800387175).',
            'en',
        ));
        $this->fake->extractionResponse = ['iban' => '[IBAN-1]', 'vat_id' => '[VATID-1]', 'phone' => '[PHONE-1]'];
        $extracted = app(AiInvocationService::class)->invoke($this->organization, self::EXTRACT, new ExtractRequest(
            'Rechnung, IBAN ' . self::IBAN_SECOND . ', USt-IdNr. DE800387175, Tel. 030 23125456',
            ['iban' => 'IBAN', 'vat_id' => 'USt-IdNr.', 'phone' => 'Telefon'],
        ));

        $this->assertSame('Bitte auf [IBAN-1] überweisen (USt-IdNr. [VATID-1]).', $this->fake->calls[0]['request']->text ?? null);
        $this->assertInstanceOf(AiTranslationResult::class, $translated->result);
        $this->assertSame('Please transfer to ' . self::IBAN_SECOND . ' (VAT ID DE800387175).', $translated->result->text);
        $this->assertInstanceOf(AiExtractionResult::class, $extracted->result);
        $this->assertSame(['iban' => self::IBAN_SECOND, 'vat_id' => 'DE800387175', 'phone' => '030 23125456'], $extracted->result->values);
    }
}
