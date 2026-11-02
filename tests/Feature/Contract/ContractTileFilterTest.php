<?php
/*
 * Created on   : Mon Nov 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ContractTileFilterTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Contract;

use App\Enums\Contract\{ContractKind, ContractPartnerType, ContractStatus, ContractTermKind, IndexationMethod};
use App\Models\Contract\Contract;
use App\Models\Platform\User;
use App\Services\Contract\ContractService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Kacheln der Vertragsliste führen zu dem, was sie zählen (ux-pattern-katalog
 * §4, Vollscan 2026-11-02 gap-06): „Laufende Verträge“ (Entwurf + aktiv) und
 * „Enden in ≤ 3 Monaten“ filtern die Liste über `show`, die Filterleiste
 * bietet denselben Wert an, die Kachelwerte bleiben stehen.
 */
final class ContractTileFilterTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;

    protected function setUp(): void {
        parent::setUp();
        $this->travelTo('2026-11-02 10:00:00');
        $this->setUpOrganization();
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
    }

    private function contract(string $title, ContractStatus $status, ?string $endsOn = null): Contract {
        $contract = app(ContractService::class)->create($this->organization, $this->admin, [
            'kind' => ContractKind::Rent->value, 'partner_type' => ContractPartnerType::Other->value, 'partner_name' => 'Partner',
            'term_kind' => ContractTermKind::OpenEnded->value, 'starts_on' => '2026-01-01', 'indexation_method' => IndexationMethod::None->value,
            'currency' => 'EUR', 'title' => $title, 'value_amount' => 100, 'value_period' => 'monthly',
        ]);
        $contract->forceFill(['status' => $status->value, 'ends_on' => $endsOn])->save();

        return $contract;
    }

    public function test_tiles_filter_the_list_and_mark_the_active_filter(): void {
        $this->contract('Hallenmiete läuft', ContractStatus::Active, '2027-12-31');
        $this->contract('Wartungsvertrag endet bald', ContractStatus::Active, '2026-12-15');
        // Gekündigt zählt bis zum Ende als laufend (Contract::scopeOpen); beendet nicht.
        $this->contract('Altvertrag beendet', ContractStatus::Ended, '2026-01-31');

        $this->actingAs($this->admin)->get(route('contracts.index'))->assertOk()
            ->assertSee(route('contracts.index', ['show' => 'open']))
            ->assertSee(route('contracts.index', ['show' => 'ending_soon']))
            ->assertDontSee('ring-primary/40');

        $this->actingAs($this->admin)->get(route('contracts.index', ['show' => 'open']))->assertOk()
            ->assertSee('Hallenmiete läuft')->assertSee('Wartungsvertrag endet bald')->assertDontSee('Altvertrag beendet')
            ->assertSee('ring-primary/40')
            ->assertSee('<option value="open" selected', false);

        $this->actingAs($this->admin)->get(route('contracts.index', ['show' => 'ending_soon']))->assertOk()
            ->assertSee('Wartungsvertrag endet bald')->assertDontSee('Hallenmiete läuft')->assertDontSee('Altvertrag beendet');
    }

    public function test_tile_values_are_counted_over_the_whole_stock(): void {
        $this->contract('Hallenmiete läuft', ContractStatus::Active, '2027-12-31');
        $this->contract('Wartungsvertrag endet bald', ContractStatus::Active, '2026-12-15');

        // Gefiltert auf „endet bald“ zeigt die Kachel „Laufende Verträge“ weiter beide.
        $this->actingAs($this->admin)->get(route('contracts.index', ['show' => 'ending_soon']))->assertOk()
            ->assertSeeInOrder(['Laufende Verträge', '2', 'Enden in ≤ 3 Monaten', '1']);
    }

    public function test_unknown_show_value_lists_everything(): void {
        $this->contract('Hallenmiete läuft', ContractStatus::Active, '2027-12-31');
        $this->contract('Altvertrag beendet', ContractStatus::Ended, '2026-01-31');

        $this->actingAs($this->admin)->get(route('contracts.index', ['show' => 'alles']))->assertOk()
            ->assertSee('Hallenmiete läuft')->assertSee('Altvertrag beendet')->assertDontSee('ring-primary/40');
    }
}
