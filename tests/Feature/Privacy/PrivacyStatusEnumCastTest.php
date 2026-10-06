<?php
/*
 * Created on   : Mon Oct 05 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PrivacyStatusEnumCastTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Privacy;

use App\Enums\Privacy\{ComplianceFindingStatus, IncidentType, MeasureStatus};
use App\Models\Platform\{Organization, User};
use App\Models\Privacy\{ComplianceFinding, Measure};
use App\Services\Privacy\{ComplianceAnalysisService, DataProtectionPermissions, IncidentService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Konsolidierungs-Audit 2026-10, k3-10 (Welle 7): Lückenbefund und
 * Folgemaßnahme führen ihren Status als Enum. Ein Vergleich gegen die frühere
 * Zeichenkette ist danach still falsch — eine erledigte Maßnahme stünde wieder
 * als überfällig und offen in der Akte.
 */
final class PrivacyStatusEnumCastTest extends TestCase {
    use RefreshDatabase;

    private Organization $org;

    private User $officer;

    protected function setUp(): void {
        parent::setUp();
        $this->travelTo('2026-10-05 12:00:00');
        config()->set('dataprotection.key', base64_encode(random_bytes(32)));
        $this->org = Organization::factory()->create();
        DataProtectionPermissions::seedOrganization($this->org);
        $this->officer = User::factory()->create(['organization_id' => $this->org->id]);
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->org->id);
        $this->officer->assignRole(DataProtectionPermissions::ROLE_DATENSCHUTZ);
    }

    protected function tearDown(): void {
        app(PermissionRegistrar::class)->setPermissionsTeamId(null);
        parent::tearDown();
    }

    /** @param array<string, mixed> $attributes */
    private function finding(ComplianceFindingStatus $status, array $attributes = []): ComplianceFinding {
        return ComplianceFinding::query()->create(array_replace([
            'organization_id' => $this->org->id,
            'requirement_key' => 'regel_' . $status->value,
            'label' => 'Anforderung ' . $status->value,
            'status' => $status,
            'auto_detected' => true,
        ], $attributes));
    }

    private function assertBadge(string $tone, string $label, int $times, string $html): void {
        $this->assertSame($times, preg_match_all('/badge-' . $tone . '[^>]*>\s*' . preg_quote(e($label), '/') . '\s*</u', $html), "Abzeichen „{$label}“ im Ton {$tone}");
    }

    // ── Lückenbefunde ────────────────────────────────────────────────────

    public function test_compliance_page_shows_tone_and_label_and_preselects_the_decision(): void {
        foreach (ComplianceFindingStatus::cases() as $status) {
            $this->finding($status);
        }

        $html = (string) $this->actingAs($this->officer)->get(route('dataprotection.compliance.index'))->assertOk()->getContent();

        $tones = ['missing' => 'error', 'expiring' => 'warning', 'required' => 'warning', 'in_review' => 'info', 'deviation_accepted' => 'ghost', 'present' => 'success', 'not_applicable' => 'ghost'];
        foreach (ComplianceFindingStatus::cases() as $status) {
            $this->assertSame($tones[$status->value], $status->tone());
            // Einmal in der Ampel, einmal in der Zeile.
            $this->assertBadge($status->tone(), $status->label(), 2, $html);
        }
        foreach (ComplianceFindingStatus::manual() as $option) {
            $this->assertSame(1, substr_count($html, '<option value="' . $option->value . '" selected>'), $option->value);
        }
        $this->assertSame(1, substr_count($html, '<option value="missing" selected>' . e((string) __('Wieder offen')) . '</option>'));
        $this->assertSame(1, substr_count($html, '<option value="present" selected>' . e(ComplianceFindingStatus::Present->label()) . '</option>'));
        // Analysezustände sind keine Entscheidung.
        $this->assertSame(0, substr_count($html, '<option value="expiring"'));
        $this->assertSame(0, substr_count($html, '<option value="required"'));
    }

    public function test_decision_starts_empty_when_the_status_is_no_decision(): void {
        $placeholder = '<option value="" selected disabled>' . e((string) __('Bitte wählen')) . '</option>';
        $findings = [];
        foreach (ComplianceFindingStatus::cases() as $status) {
            $findings[$status->value] = $this->finding($status);
        }

        $html = (string) $this->actingAs($this->officer)->get(route('dataprotection.compliance.index'))->assertOk()->getContent();

        foreach ($findings as $finding) {
            $this->assertSame(1, preg_match('/action="' . preg_quote(e(route('dataprotection.compliance.update', $finding)), '/') . '".*?<\/form>/su', $html, $form), $finding->status->value);
            if ($finding->status->isManual()) {
                $this->assertStringNotContainsString($placeholder, $form[0], $finding->status->value);
                $this->assertStringNotContainsString(' required', explode('</select>', $form[0])[0], $finding->status->value);
                $this->assertSame(1, substr_count($form[0], '<option value="' . $finding->status->value . '" selected>'), $finding->status->value);

                continue;
            }
            // „Läuft ab“ und „Erforderlich“: keine Option passt — das Feld darf nicht „Vorhanden“ zeigen.
            $this->assertStringContainsString($placeholder, $form[0], $finding->status->value);
            $this->assertSame(1, substr_count($form[0], ' selected'), $finding->status->value);
            $this->assertSame(1, preg_match('/<select name="status"[^>]* required/', $form[0]), $finding->status->value);
            $this->assertBadge($finding->status->tone(), $finding->status->label(), 2, $html);
        }
        $this->assertSame(['expiring', 'required'], array_values(array_filter(array_keys($findings), static fn (string $value): bool => ! ComplianceFindingStatus::from($value)->isManual())));

        // Ohne Wahl abgesendet: Fehler, der Befund bleibt, wie die Analyse ihn gesetzt hat.
        foreach (['expiring', 'required'] as $value) {
            foreach ([['status' => ''], []] as $data) {
                $this->actingAs($this->officer)->put(route('dataprotection.compliance.update', $findings[$value]), $data + ['justification' => 'Grund'])
                    ->assertSessionHasErrors('status');
            }
            $fresh = $findings[$value]->fresh();
            $this->assertSame($value, $fresh->status->value);
            $this->assertTrue((bool) $fresh->auto_detected);
            $this->assertNull($fresh->justification);
        }
    }

    public function test_decision_accepts_exactly_the_manual_statuses(): void {
        $finding = $this->finding(ComplianceFindingStatus::Expiring);
        $update = fn (array $data) => $this->actingAs($this->officer)->put(route('dataprotection.compliance.update', $finding), $data);

        foreach (['expiring', 'required', 'unbekannt', 'PRESENT'] as $rejected) {
            $update(['status' => $rejected, 'justification' => 'Grund'])->assertSessionHasErrors('status');
        }
        $this->assertSame(ComplianceFindingStatus::Expiring, $finding->fresh()->status);
        $this->assertTrue((bool) $finding->fresh()->auto_detected);

        $this->assertSame(['present', 'in_review', 'not_applicable', 'deviation_accepted', 'missing'], array_column(ComplianceFindingStatus::manual(), 'value'));
        foreach (ComplianceFindingStatus::manual() as $status) {
            $update(['status' => $status->value, 'justification' => 'Grund'])->assertSessionHasNoErrors();
            $this->assertSame($status, $finding->fresh()->status);
        }
        $this->assertFalse((bool) $finding->fresh()->auto_detected);
    }

    public function test_justification_is_required_for_not_applicable_and_accepted_deviation(): void {
        $finding = $this->finding(ComplianceFindingStatus::Missing);
        $update = fn (string $status) => $this->actingAs($this->officer)->put(route('dataprotection.compliance.update', $finding), ['status' => $status]);

        foreach (ComplianceFindingStatus::manual() as $status) {
            $response = $update($status->value);
            $needs = in_array($status, [ComplianceFindingStatus::NotApplicable, ComplianceFindingStatus::DeviationAccepted], true);
            $this->assertSame($needs, $status->needsJustification());
            $needs ? $response->assertSessionHasErrors('justification') : $response->assertSessionHasNoErrors();
        }
    }

    public function test_reanalysis_closes_only_the_gaps_it_detected_itself(): void {
        $detected = [$this->finding(ComplianceFindingStatus::Missing), $this->finding(ComplianceFindingStatus::Expiring)];
        $kept = [
            $this->finding(ComplianceFindingStatus::Required),
            $this->finding(ComplianceFindingStatus::InReview),
            $this->finding(ComplianceFindingStatus::Missing, ['requirement_key' => 'von_hand', 'auto_detected' => false]),
        ];

        $this->assertSame(0, app(ComplianceAnalysisService::class)->run($this->org));

        foreach ($detected as $finding) {
            $this->assertSame(ComplianceFindingStatus::Present, $finding->fresh()->status);
        }
        $this->assertSame(
            [ComplianceFindingStatus::Required, ComplianceFindingStatus::InReview, ComplianceFindingStatus::Missing],
            array_map(static fn (ComplianceFinding $finding): ComplianceFindingStatus => $finding->fresh()->status, $kept),
        );
    }

    // ── Folgemaßnahmen ───────────────────────────────────────────────────

    public function test_incident_file_shows_done_measures_as_done_and_not_as_overdue(): void {
        $service = app(IncidentService::class);
        $incident = $service->open($this->org, IncidentType::Loss, 'Laptop verloren');
        $open = $service->addMeasure($incident, 'Gerät sperren', null, Carbon::parse('2026-10-04'), $this->officer);
        $done = $service->addMeasure($incident, 'Kennwörter ändern', null, Carbon::parse('2026-10-03'), $this->officer);
        $service->completeMeasure($done, $this->officer);

        $this->assertTrue($open->fresh()->isOverdue());
        $this->assertFalse($done->fresh()->isOverdue());

        $html = (string) $this->actingAs($this->officer)->get(route('dataprotection.incidents.show', $incident))->assertOk()->getContent();

        $this->assertSame(1, substr_count($html, 'action="' . route('dataprotection.incidents.measure.complete', [$incident, $open]) . '"'));
        $this->assertSame(0, substr_count($html, 'action="' . route('dataprotection.incidents.measure.complete', [$incident, $done]) . '"'));
        $this->assertSame(1, preg_match_all('/badge-success[^>]*>\s*' . preg_quote(e((string) __('erledigt')), '/') . '\s*</u', $html));
        $this->assertSame(1, substr_count($html, '<span class="text-error">(' . __('bis') . ' ' . $open->due_at->fdate() . ')'));
        $this->assertSame(1, substr_count($html, '<span class="text-muted">(' . __('bis') . ' ' . $done->due_at->fdate() . ')'));
    }

    public function test_measure_is_opened_and_completed_through_the_incident_file(): void {
        $incident = app(IncidentService::class)->open($this->org, IncidentType::Loss, 'Laptop verloren');

        $this->actingAs($this->officer)->post(route('dataprotection.incidents.measure.store', $incident), ['title' => 'Gerät sperren', 'due_at' => '2026-10-10'])->assertSessionHasNoErrors();
        $measure = Measure::query()->sole();
        $this->assertSame(MeasureStatus::Open, $measure->status);
        $this->assertNull($measure->completed_at);

        $this->actingAs($this->officer)->post(route('dataprotection.incidents.measure.complete', [$incident, $measure]))->assertSessionHasNoErrors();
        $this->assertSame(MeasureStatus::Done, $measure->fresh()->status);
        $this->assertSame('2026-10-05 12:00:00', $measure->fresh()->completed_at->toDateTimeString());
        $this->assertSame(['open', 'done'], array_column(MeasureStatus::cases(), 'value'));
    }
}
