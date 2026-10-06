<?php
/*
 * Created on   : Mon Oct 05 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TaxRuleStatusEnumCastTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Invoicing;

use App\Enums\Finance\TaxRuleStatus;
use App\Models\Finance\TaxRule;
use App\Models\Platform\{Organization, User};
use App\Services\Invoicing\TaxResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Konsolidierungs-Audit 2026-10, k3-10 (Welle 8): die Steuerregel führt ihren
 * Stand als Enum. Gegen die frühere Zeichenkette verglichen, erschiene jede
 * Regel ausgegraut und ließe sich nicht mehr stilllegen.
 */
final class TaxRuleStatusEnumCastTest extends TestCase {
    use RefreshDatabase;

    private Organization $org;

    private User $admin;

    protected function setUp(): void {
        parent::setUp();
        $this->travelTo('2026-10-05 12:00:00');
        $this->org = Organization::factory()->create();
        app()->instance('currentOrganization', $this->org);
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->org->id]);
    }

    private function rule(TaxRuleStatus $status, string $rate, string $from): TaxRule {
        return TaxRule::query()->create([
            'organization_id' => $this->org->id,
            'country' => 'DE', 'category' => 'services', 'rate_type' => 'standard',
            'rate' => $rate, 'valid_from' => $from, 'status' => $status,
        ]);
    }

    public function test_matrix_dims_retired_rules_and_offers_retirement_for_active_ones(): void {
        $active = $this->rule(TaxRuleStatus::Active, '18.00', '2026-01-01');
        $retired = $this->rule(TaxRuleStatus::Retired, '17.00', '2025-01-01');

        $html = (string) $this->actingAs($this->admin)->get(route('finance.tax-rules.index'))->assertOk()->getContent();

        foreach ([$active, $retired] as $rule) {
            $this->assertSame($rule === $active, str_contains($html, 'action="' . route('finance.tax-rules.retire', $rule) . '"'), "Stilllegen bei {$rule->status->value}.");
        }
        $this->assertSame(1, substr_count($html, '<tr class="opacity-50">'));
        foreach ([$active, $retired] as $rule) {
            $this->assertSame(1, preg_match('/badge-outline"[^>]*>\s*' . preg_quote(e($rule->status->label()), '/') . '\s*</u', $html), "Kein Abzeichen für {$rule->status->value}.");
        }
    }

    public function test_retired_rule_no_longer_resolves(): void {
        $rule = $this->rule(TaxRuleStatus::Active, '18.00', '2026-01-01');
        $resolver = app(TaxResolver::class);
        $this->assertTrue($resolver->ruleFor($this->org->id, 'DE', 'services', 'standard', now())?->is($rule));

        $this->actingAs($this->admin)->post(route('finance.tax-rules.retire', $rule))->assertSessionHasNoErrors();

        $this->assertSame(TaxRuleStatus::Retired, $rule->fresh()->status);
        $this->assertDatabaseHas('tax_rules', ['id' => $rule->id, 'status' => 'retired']);
        $this->assertNull($resolver->ruleFor($this->org->id, 'DE', 'services', 'standard', now()));
    }
}
