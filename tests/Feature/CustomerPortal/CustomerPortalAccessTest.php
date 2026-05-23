<?php
/*
 * Created on   : Sat May 30 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CustomerPortalAccessTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\CustomerPortal;

use App\Models\Customer;
use App\Models\DiaryEntry;
use App\Models\Invoice;
use App\Models\OpenIssue;
use App\Models\User;
use Database\Seeders\PermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

class CustomerPortalAccessTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private Customer $customer;

    private User $portalUser;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->seed(PermissionsSeeder::class);
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);

        $this->customer = Customer::factory()->create([
            'organization_id' => $this->organization->id,
        ]);
        $this->portalUser = User::factory()
            ->kunde((int) $this->customer->id, (int) $this->organization->id)
            ->create([
                'organization_id' => $this->organization->id,
                'email' => 'portal@example.test',
                'password' => Hash::make('secret-pass'),
            ]);
    }

    public function test_customer_can_login_through_customer_guard(): void {
        $response = $this->post(route('customer.login.attempt'), [
            'email' => 'portal@example.test',
            'password' => 'secret-pass',
        ]);
        $response->assertRedirect(route('customer.dashboard'));
        $this->assertSame($this->portalUser->id, auth('customer')->id());
    }

    public function test_customer_cannot_login_through_internal_guard(): void {
        // Internal-Login darf einen Portal-Account NICHT akzeptieren.
        $response = $this->post(route('login'), [
            'username' => 'portal@example.test',
            'password' => 'secret-pass',
        ]);

        $this->assertFalse(auth('web')->check(), 'Portal-Account darf intern nicht eingeloggt sein.');
        $response->assertStatus(302);
    }

    public function test_internal_user_cannot_login_through_customer_guard(): void {
        $internal = User::factory()->user()->create([
            'organization_id' => $this->organization->id,
            'email' => 'intern@example.test',
            'password' => Hash::make('intern-pass'),
        ]);

        $response = $this->post(route('customer.login.attempt'), [
            'email' => 'intern@example.test',
            'password' => 'intern-pass',
        ]);
        $response->assertSessionHasErrors('email');
        $this->assertFalse(auth('customer')->check());
        $this->assertNotNull($internal->id);
    }

    public function test_guest_is_redirected_to_customer_login_for_protected_routes(): void {
        $this->get(route('customer.dashboard'))->assertRedirect(route('customer.login'));
        $this->get(route('customer.diary.index'))->assertRedirect(route('customer.login'));
    }

    public function test_customer_dashboard_renders_with_own_stats_only(): void {
        $otherCustomer = Customer::factory()->create(['organization_id' => $this->organization->id]);
        DiaryEntry::factory()->count(3)->create([
            'organization_id' => $this->organization->id,
            'customer_id' => $this->customer->id,
        ]);
        DiaryEntry::factory()->create([
            'organization_id' => $this->organization->id,
            'customer_id' => $otherCustomer->id,
        ]);

        $this->actingAs($this->portalUser, 'customer');
        $response = $this->get(route('customer.dashboard'));
        $response->assertOk();
        $response->assertSee((string) 3); // eigene Tagebuch-Einträge
    }

    public function test_customer_diary_list_only_returns_own_entries(): void {
        $otherCustomer = Customer::factory()->create(['organization_id' => $this->organization->id]);
        DiaryEntry::factory()->create([
            'organization_id' => $this->organization->id,
            'customer_id' => $this->customer->id,
            'title' => 'Mein Eintrag',
        ]);
        DiaryEntry::factory()->create([
            'organization_id' => $this->organization->id,
            'customer_id' => $otherCustomer->id,
            'title' => 'Fremder Eintrag',
        ]);

        $this->actingAs($this->portalUser, 'customer');
        $response = $this->get(route('customer.diary.index'));
        $response->assertOk();
        $response->assertSee('Mein Eintrag');
        $response->assertDontSee('Fremder Eintrag');
    }

    public function test_customer_cannot_reach_internal_routes(): void {
        // Manueller Login statt actingAs(), damit der Default-Guard `web`
        // bleibt und die `auth`-Middleware der internen Routen korrekt
        // gegen den web-Guard prüft (nicht gegen den customer-Guard).
        Auth::guard('customer')->login($this->portalUser);

        $response = $this->get('/customers');
        $this->assertNotSame(200, $response->getStatusCode(), 'Portal-User darf interne Kunden-Liste nicht sehen.');
    }

    public function test_logout_terminates_customer_session(): void {
        $this->actingAs($this->portalUser, 'customer');
        $this->post(route('customer.logout'))->assertRedirect(route('customer.login'));
        $this->assertFalse(auth('customer')->check());
    }

    public function test_invoice_list_is_scoped_to_own_customer(): void {
        $otherCustomer = Customer::factory()->create(['organization_id' => $this->organization->id]);
        Invoice::create([
            'organization_id' => $this->organization->id,
            'customer_id' => $this->customer->id,
            'number' => 'INV-OWN-001',
            'status' => Invoice::STATUS_ISSUED,
            'currency' => 'EUR',
            'subtotal' => '100.00',
            'tax_rate' => '19.00',
            'tax_amount' => '19.00',
            'total' => '119.00',
        ]);
        Invoice::create([
            'organization_id' => $this->organization->id,
            'customer_id' => $otherCustomer->id,
            'number' => 'INV-OTHER-001',
            'status' => Invoice::STATUS_ISSUED,
            'currency' => 'EUR',
            'subtotal' => '50.00',
            'tax_rate' => '19.00',
            'tax_amount' => '9.50',
            'total' => '59.50',
        ]);

        $this->actingAs($this->portalUser, 'customer');
        $response = $this->get(route('customer.invoices.index'));
        $response->assertOk();
        $response->assertSee('INV-OWN-001');
        $response->assertDontSee('INV-OTHER-001');
    }

    public function test_customer_only_sees_open_issues_with_customer_visibility(): void {
        $otherCustomer = Customer::factory()->create(['organization_id' => $this->organization->id]);

        $ownDiary = DiaryEntry::factory()->create([
            'organization_id' => $this->organization->id,
            'customer_id' => $this->customer->id,
        ]);
        $otherDiary = DiaryEntry::factory()->create([
            'organization_id' => $this->organization->id,
            'customer_id' => $otherCustomer->id,
        ]);

        // Sichtbar: customer-visibility, eigener Diary-Subject
        OpenIssue::factory()->for($ownDiary, 'subject')->create([
            'organization_id' => $this->organization->id,
            'title' => 'Sichtbar fuer Kunde',
            'visibility' => \App\Enums\OpenIssue\OpenIssueVisibility::Customer->value,
        ]);
        // Unsichtbar: internal-visibility am eigenen Diary
        OpenIssue::factory()->for($ownDiary, 'subject')->create([
            'organization_id' => $this->organization->id,
            'title' => 'Intern Nur Mitarbeiter',
            'visibility' => \App\Enums\OpenIssue\OpenIssueVisibility::Internal->value,
        ]);
        // Unsichtbar: customer-visibility am fremden Diary
        OpenIssue::factory()->for($otherDiary, 'subject')->create([
            'organization_id' => $this->organization->id,
            'title' => 'Fremder Kundenpunkt',
            'visibility' => \App\Enums\OpenIssue\OpenIssueVisibility::Customer->value,
        ]);

        $this->actingAs($this->portalUser, 'customer');
        $response = $this->get(route('customer.open-issues.index'));
        $response->assertOk();
        $response->assertSee('Sichtbar fuer Kunde');
        $response->assertDontSee('Intern Nur Mitarbeiter');
        $response->assertDontSee('Fremder Kundenpunkt');
    }
}
