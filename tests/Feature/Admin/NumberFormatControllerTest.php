<?php
/*
 * Created on   : Wed May 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : NumberFormatControllerTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Admin;

use App\Enums\Finance\BillingMode;
use App\Enums\Numbering\NumberScope;
use App\Models\Customer\Customer;
use App\Models\Numbering\NumberFormat;
use App\Models\Platform\{Organization, PluginSetting, User};
use App\Plugins\Lexoffice\LexofficePlugin;
use App\Services\Numbering\NumberSequenceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Seite „Nummernkreise“ (Phase 144, MVP-1147–1149): alle Kreise in einem
 * Absenden, Trenner für alle Kreise, Sperre bei Vergabe durch ein Programm.
 */
class NumberFormatControllerTest extends TestCase {
    use RefreshDatabase;

    private Organization $org;

    private User $admin;

    protected function setUp(): void {
        parent::setUp();
        $this->org = Organization::factory()->create();
        $this->app->instance('currentOrganization', $this->org);
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->org->id]);
    }

    /**
     * Zeile so, wie das Formular sie sendet.
     *
     * @return array<string, int|string>
     */
    private function row(NumberScope $scope): array {
        $format = app(NumberSequenceService::class)->resolveFormat((int) $this->org->id, $scope);

        return [
            'prefix' => (string) $format->prefix,
            'prefix_separator' => (string) $format->prefix_separator,
            'include_year' => $format->include_year ? '1' : '0',
            'year_separator' => (string) $format->year_separator,
            'padding' => (int) $format->padding,
            'reset_per_year' => $format->reset_per_year ? '1' : '0',
            'starts_at' => (int) $format->starts_at,
        ];
    }

    private function persisted(NumberScope $scope): ?NumberFormat {
        return NumberFormat::query()->where('organization_id', $this->org->id)->where('scope', $scope->value)->first();
    }

    public function test_index_lists_all_scopes_with_defaults(): void {
        $response = $this->actingAs($this->admin)->get(route('admin.number-formats.index'));
        $response->assertOk();

        $rows = $response->viewData('rows');
        $this->assertIsArray($rows);
        $this->assertCount(count(NumberScope::cases()), $rows);
    }

    public function test_one_submit_saves_changed_scopes_and_keeps_defaults(): void {
        $this->actingAs($this->admin)
            ->put(route('admin.number-formats.update'), ['formats' => [
                NumberScope::Asset->value => ['prefix' => 'INV', 'padding' => 6] + $this->row(NumberScope::Asset),
                NumberScope::Quote->value => ['starts_at' => 100] + $this->row(NumberScope::Quote),
                NumberScope::Article->value => $this->row(NumberScope::Article),
            ]])
            ->assertRedirect(route('admin.number-formats.index'))
            ->assertSessionHas('success', trans_choice('numbering.flash.saved', 2, ['count' => 2]));

        $asset = $this->persisted(NumberScope::Asset);
        $quote = $this->persisted(NumberScope::Quote);
        $this->assertNotNull($asset);
        $this->assertNotNull($quote);
        $this->assertSame(['INV', 6], [$asset->prefix, $asset->padding]);
        $this->assertSame(100, $quote->starts_at);
        $this->assertNull($this->persisted(NumberScope::Article), 'Unveränderter Kreis bleibt Standard.');

        $next = app(NumberSequenceService::class)->next($this->org, NumberScope::Asset, now());
        $this->assertStringStartsWith('INV-', $next);
        $this->assertSame(6, strlen(substr($next, strrpos($next, '-') + 1)));
    }

    public function test_unchanged_submit_reports_no_changes(): void {
        $this->actingAs($this->admin)
            ->put(route('admin.number-formats.update'), ['formats' => [NumberScope::Asset->value => $this->row(NumberScope::Asset)]])
            ->assertSessionHas('success', __('numbering.flash.unchanged'));

        $this->assertDatabaseCount('number_formats', 0);
    }

    public function test_error_names_the_scope(): void {
        $attribute = __('numbering.field.row', ['field' => __('validation.attributes')['formats.*.padding'], 'scope' => NumberScope::Quote->label()]);

        $this->actingAs($this->admin)
            ->from(route('admin.number-formats.index'))
            ->put(route('admin.number-formats.update'), ['formats' => [NumberScope::Quote->value => ['padding' => 0] + $this->row(NumberScope::Quote)]])
            ->assertRedirect(route('admin.number-formats.index'))
            ->assertSessionHasErrors(['formats.quote.padding' => __('validation.min.numeric', ['attribute' => $attribute, 'min' => 1])]);

        $this->assertDatabaseCount('number_formats', 0);
    }

    public function test_unknown_scope_is_rejected(): void {
        $this->actingAs($this->admin)
            ->put(route('admin.number-formats.update'), ['formats' => ['nope' => $this->row(NumberScope::Asset)]])
            ->assertSessionHasErrors('formats');
    }

    public function test_separators_apply_to_open_scopes_only(): void {
        $this->lexofficeIssuesDocumentNumbers();

        $this->actingAs($this->admin)
            ->put(route('admin.number-formats.update'), [
                'separators' => ['prefix' => '/', 'year' => ''],
                'apply_separators' => '1',
                'formats' => [
                    NumberScope::Asset->value => $this->row(NumberScope::Asset),
                    NumberScope::Customer->value => $this->row(NumberScope::Customer),
                    // Eine vor dem Umschalten geöffnete Seite sendet die Felder noch mit.
                    NumberScope::Invoice->value => ['prefix' => 'X'] + $this->row(NumberScope::Invoice),
                ],
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(['/', ''], [$this->persisted(NumberScope::Asset)?->prefix_separator, $this->persisted(NumberScope::Asset)?->year_separator]);
        $this->assertSame(['/', ''], [$this->persisted(NumberScope::Customer)?->prefix_separator, $this->persisted(NumberScope::Customer)?->year_separator]);
        $invoice = $this->persisted(NumberScope::Invoice);
        $this->assertSame(['R', '', '-'], [$invoice?->prefix, $invoice?->prefix_separator, $invoice?->year_separator]);
        $this->assertNull($this->persisted(NumberScope::Article), 'Nicht gesendete Kreise bleiben unberührt.');

        $this->assertSame('AS/2026', substr(app(NumberSequenceService::class)->peekNext($this->org, NumberScope::Asset, now()->setDate(2026, 6, 1)), 0, 7));
    }

    public function test_lexoffice_switch_locks_document_scopes(): void {
        $this->lexofficeIssuesDocumentNumbers();

        $response = $this->actingAs($this->admin)->get(route('admin.number-formats.index'))->assertOk();

        $rows = collect((array) $response->viewData('rows'))->keyBy(fn (array $row): string => $row['scope']->value);
        foreach ([NumberScope::Invoice, NumberScope::CreditNote, NumberScope::Cancellation] as $scope) {
            $this->assertSame('Lexoffice', $rows[$scope->value]['lockedBy'], $scope->value);
            $this->assertSame('Lexoffice', $rows[$scope->value]['issuer']);
            $this->assertNull($rows[$scope->value]['preview']);
            $this->assertSame([__('numbering.preview.program', ['program' => 'Lexoffice'])], $rows[$scope->value]['details']);
        }
        $this->assertNull($rows[NumberScope::Quote->value]['lockedBy']);
        $response->assertSee('name="formats[invoice][include_year]" value="1" form="number-formats-form" disabled', false);
    }

    public function test_external_billing_route_locks_document_scopes_unless_a_customer_bills_locally(): void {
        $this->org->update(['settings' => ['billing_mode' => BillingMode::SevDesk->value]]);

        $rows = $this->rows();
        $this->assertSame('sevDesk', $rows[NumberScope::Invoice->value]['lockedBy']);
        $this->assertSame(__('numbering.source.billing', ['program' => 'sevDesk']), $rows[NumberScope::Invoice->value]['issuer']);
        $this->assertSame([], $rows[NumberScope::Invoice->value]['issuerNotes']);
        $this->assertNull($rows[NumberScope::Customer->value]['lockedBy']);

        Customer::factory()->create(['organization_id' => $this->org->id, 'billing_mode' => BillingMode::Workdiary]);

        $rows = $this->rows();
        $this->assertNull($rows[NumberScope::Invoice->value]['lockedBy']);
        $this->assertNotNull($rows[NumberScope::Invoice->value]['preview']);
        $this->assertSame([trans_choice('numbering.source.deviation', 1, ['program' => 'workDiary', 'count' => 1])], $rows[NumberScope::Invoice->value]['issuerNotes']);
    }

    public function test_update_requires_manage_permission(): void {
        $member = User::factory()->user()->create(['organization_id' => $this->org->id]);

        $this->actingAs($member)
            ->put(route('admin.number-formats.update'), ['formats' => [NumberScope::Asset->value => $this->row(NumberScope::Asset)]])
            ->assertForbidden();

        $this->assertDatabaseCount('number_formats', 0);
    }

    private function lexofficeIssuesDocumentNumbers(): void {
        PluginSetting::create([
            'organization_id' => $this->org->id,
            'plugin_id' => LexofficePlugin::ID,
            'enabled' => true,
            'settings' => ['api_key' => 'k'],
        ]);
        foreach ([NumberScope::Invoice, NumberScope::CreditNote, NumberScope::Cancellation] as $scope) {
            app(NumberSequenceService::class)->setFormat($this->org, $scope, ['source' => LexofficePlugin::ID]);
        }
    }

    /** @return array<string, array<string, mixed>> */
    private function rows(): array {
        $response = $this->actingAs($this->admin)->get(route('admin.number-formats.index'))->assertOk();

        return collect((array) $response->viewData('rows'))->keyBy(fn (array $row): string => $row['scope']->value)->all();
    }
}
