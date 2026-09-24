<?php
/*
 * Created on   : Tue Sep 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ClassificationRequirementEnforcementTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Classification;

use App\Enums\Classification\{ClassificationRequirementPhase, ClassificationRequirementSeverity};
use App\Enums\Diary\Status;
use App\Enums\Protocol\{ProtocolStatus, ProtocolType};
use App\Exceptions\ClassificationRequirementException;
use App\Models\Classification\{ClassificationRequirement, EntryType};
use App\Models\Diary\DiaryEntry;
use App\Models\Platform\{Organization, User};
use App\Models\Protocol\Protocol;
use App\Services\Diary\OrderService;
use App\Services\Protocol\ProtocolService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Durchsetzung der Pflichtklassifikationen (Vollscan 2026-09-15, `P12-30` /
 * MVP-795). Vor diesem Paket waren Pflichtklassifikationen pflegbar und
 * wurden als Hinweis angezeigt, aber an keiner Stelle erzwungen: der Prüfer
 * hatte ausserhalb der reinen Hinweisanzeige keinen einzigen Aufrufer.
 *
 * Geprüft werden die drei Phasen — Anlage, Auftragsabschluss, Signatur —
 * jeweils in beide Richtungen, plus die Abgrenzung: `soft` bleibt Hinweis,
 * und die Prüfanforderung am Protokoll ist ein Zwischenschritt ohne
 * Klassifikationspflicht.
 */
class ClassificationRequirementEnforcementTest extends TestCase {
    use RefreshDatabase;

    private Organization $org;

    private User $actor;

    private EntryType $entryType;

    protected function setUp(): void {
        parent::setUp();

        $this->org = Organization::factory()->create();
        $this->entryType = EntryType::query()->withoutGlobalScopes()
            ->where('organization_id', $this->org->id)
            ->where('slug', EntryType::SLUG_SERVICE)
            ->firstOrFail();

        // Die typgesteuerten Pflichtfelder (Kunde, Adresse, Termin, Tour) sind
        // hier nicht Gegenstand der Prüfung — sie würden die Anlage schon vor
        // der Klassifikationsprüfung abweisen.
        $this->entryType->update([
            'requires_customer' => false,
            'requires_address' => false,
            'requires_schedule' => false,
            'requires_tour' => false,
        ]);

        $this->actor = User::factory()->geschaeftsfuehrung()->create([
            'organization_id' => $this->org->id,
        ]);
        $this->actingAs($this->actor);
    }

    private function requireDomain(
        string $domain,
        ClassificationRequirementPhase $phase,
        ClassificationRequirementSeverity $severity = ClassificationRequirementSeverity::Hard,
    ): void {
        ClassificationRequirement::factory()->create([
            'organization_id' => $this->org->id,
            'entry_type_code' => EntryType::SLUG_SERVICE,
            'required_domain' => $domain,
            'enforce_phase' => $phase->value,
            'severity' => $severity->value,
            'min_count' => 1,
        ]);
    }

    private function entry(Status $status = Status::InProgress, ?string $priority = 'normal'): DiaryEntry {
        return DiaryEntry::factory()->create([
            'organization_id' => $this->org->id,
            'user_id' => $this->actor->id,
            'entry_type_id' => $this->entryType->id,
            'priority' => $priority,
            'status' => $status->value,
        ]);
    }

    /** @param array<string, mixed> $overrides */
    private function storePayload(array $overrides = []): array {
        return array_merge([
            'content' => 'Neuer Auftrag',
            'status' => Status::Open->value,
            'start_at' => '2030-01-15 09:00:00',
            'end_at' => '2030-01-15 10:00:00',
            'entry_type_id' => $this->entryType->id,
        ], $overrides);
    }

    // ----- Phase 1: Anlage -----

    public function test_creation_is_blocked_while_a_mandatory_classification_is_missing(): void {
        $this->requireDomain('priority', ClassificationRequirementPhase::OnCreate);

        $this->post(route('diary.store'), $this->storePayload())
            ->assertRedirect()
            ->assertSessionHasErrors('classification');

        $this->assertSame(0, DiaryEntry::query()->withoutGlobalScopes()->count());
    }

    public function test_creation_passes_once_the_mandatory_classification_is_set(): void {
        $this->requireDomain('priority', ClassificationRequirementPhase::OnCreate);

        $this->post(route('diary.store'), $this->storePayload(['priority' => 'normal']))
            ->assertSessionHasNoErrors();

        $this->assertSame(1, DiaryEntry::query()->withoutGlobalScopes()->count());
    }

    public function test_a_soft_requirement_does_not_block_creation(): void {
        $this->requireDomain('priority', ClassificationRequirementPhase::OnCreate, ClassificationRequirementSeverity::Soft);

        $this->post(route('diary.store'), $this->storePayload())
            ->assertSessionHasNoErrors();

        $this->assertSame(1, DiaryEntry::query()->withoutGlobalScopes()->count());
    }

    // ----- Phase 2: Auftragsabschluss -----

    public function test_completion_is_blocked_while_a_mandatory_classification_is_missing(): void {
        $this->requireDomain('defect_type', ClassificationRequirementPhase::BeforeComplete);
        $entry = $this->entry();

        $this->expectException(ClassificationRequirementException::class);

        try {
            app(OrderService::class)->complete($entry, $this->actor, 'Fertig');
        } finally {
            // Der Statuswechsel darf nicht stattgefunden haben.
            $this->assertSame(Status::InProgress, $entry->fresh()->status);
        }
    }

    public function test_completion_passes_without_a_matching_requirement(): void {
        $this->requireDomain('defect_type', ClassificationRequirementPhase::BeforeSign);
        $entry = $this->entry();

        app(OrderService::class)->complete($entry, $this->actor, 'Fertig');

        $this->assertSame(Status::Completed, $entry->fresh()->status);
    }

    public function test_a_soft_requirement_does_not_block_completion(): void {
        $this->requireDomain('defect_type', ClassificationRequirementPhase::BeforeComplete, ClassificationRequirementSeverity::Soft);
        $entry = $this->entry();

        app(OrderService::class)->complete($entry, $this->actor, 'Fertig');

        $this->assertSame(Status::Completed, $entry->fresh()->status);
    }

    // ----- Phase 3: Signatur -----

    public function test_signing_is_blocked_while_a_mandatory_classification_is_missing(): void {
        $this->requireDomain('defect_type', ClassificationRequirementPhase::BeforeSign);
        $protocol = $this->protocol();

        try {
            app(ProtocolService::class)->sign($protocol, $this->actor);
            $this->fail('Die Signatur hätte an der fehlenden Pflichtklassifikation scheitern müssen.');
        } catch (\App\Exceptions\ProtocolValidationException $exception) {
            $this->assertNotSame([], $exception->errors());
            $this->assertStringContainsString('Fehlertypen', implode(' ', $exception->errors()));
        }

        $this->assertNotSame(ProtocolStatus::Signed, $protocol->fresh()->status);
    }

    public function test_signing_passes_without_a_matching_requirement(): void {
        $this->requireDomain('defect_type', ClassificationRequirementPhase::BeforeComplete);
        $protocol = $this->protocol();

        app(ProtocolService::class)->sign($protocol, $this->actor);

        $this->assertSame(ProtocolStatus::Signed, $protocol->fresh()->status);
    }

    /**
     * Die Prüfanforderung ist ein Zwischenschritt: sie teilt die
     * Item-Validierung mit der Signatur, aber nicht deren
     * Klassifikationspflicht.
     */
    public function test_requesting_a_review_is_not_blocked_by_a_signing_requirement(): void {
        $this->requireDomain('defect_type', ClassificationRequirementPhase::BeforeSign);
        $protocol = $this->protocol();

        app(ProtocolService::class)->requestReview($protocol, $this->actor);

        $this->assertSame(ProtocolStatus::InReview, $protocol->fresh()->status);
    }

    private function protocol(): Protocol {
        return app(ProtocolService::class)->create($this->entry(), $this->actor, [
            'type' => ProtocolType::Service->value,
            'title' => 'Abnahme',
        ]);
    }
}
