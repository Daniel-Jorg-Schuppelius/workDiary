<?php
/*
 * Created on   : Mon Oct 05 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : OnboardingStepStateEnumCastTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Onboarding;

use App\Enums\Platform\OnboardingStepState;
use App\Models\Platform\{OnboardingProgress, User};
use App\Services\Onboarding\OnboardingChecklistResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Konsolidierungs-Audit 2026-10, k3-10 (Welle 8): der Einrichtungsschritt
 * führt seinen Stand als Enum. Gegen die frühere Zeichenkette verglichen,
 * zeigte die Seite jeden Schritt als offen und der Abgleich überschriebe
 * einen übersprungenen Schritt.
 */
final class OnboardingStepStateEnumCastTest extends TestCase {
    use RefreshDatabase;

    public function test_page_shows_each_step_with_the_badge_of_its_state(): void {
        $this->travelTo('2026-10-05 12:00:00');
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post(route('onboarding.steps.skip', ['step' => 'users.invite']), ['reason' => 'Vorerst allein'])->assertSessionHasNoErrors();

        $html = (string) $this->actingAs($admin)->get(route('onboarding.index'))->assertOk()->getContent();
        $steps = collect(app(OnboardingChecklistResolver::class)->forOrganization($admin->organization->fresh(), $admin)['steps']);

        $this->assertSame(OnboardingStepState::Skipped, $steps->firstWhere('code', 'users.invite')['state']);
        $this->assertSame(OnboardingStepState::Skipped, OnboardingProgress::query()->withoutGlobalScopes()->where('step_code', 'users.invite')->firstOrFail()->state);
        $this->assertTrue(str_contains($html, e(OnboardingStepState::Skipped->label() . ': Vorerst allein')), 'Begründung des übersprungenen Schritts fehlt.');

        foreach ([[OnboardingStepState::Done, 'success'], [OnboardingStepState::Skipped, 'ghost'], [OnboardingStepState::Open, 'warning']] as [$state, $tone]) {
            $expected = $steps->filter(fn (array $step): bool => $step['state'] === $state)->count();
            $this->assertGreaterThan(0, $expected, "Kein Schritt im Stand {$state->value}.");
            $this->assertSame($expected, preg_match_all('/badge-' . $tone . ' badge-outline"[^>]*>\s*' . preg_quote(e($state->label()), '/') . '\s*</u', $html), "Abzeichen für {$state->value}.");
        }
    }

    /** JSON-Verbraucher (Dashboard-Zusammenfassung) sehen weiter den Speicherwert. */
    public function test_checklist_serialises_the_state_as_its_stored_value(): void {
        $admin = User::factory()->admin()->create();

        $steps = json_decode((string) json_encode(app(OnboardingChecklistResolver::class)->forOrganization($admin->organization, $admin)['steps']), true);

        $this->assertContains($steps[0]['state'], ['open', 'done', 'skipped']);
    }
}
