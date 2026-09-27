<?php
/*
 * Created on   : Sun Sep 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : EmploymentContractTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Contract;

use App\Enums\Contract\{ContractKind, SigningRevisionStatus};
use App\Enums\Hr\HrDocumentCategory;
use App\Mail\AgreementLinkMail;
use App\Models\Contract\Contract;
use App\Models\Document\Document;
use App\Models\Platform\{Organization, User};
use App\Services\Hr\PersonnelFilePermissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\{Mail, Storage};
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/** MVP-939: Arbeitsvertrag über die Signaturschicht, nur für die Personalabteilung sichtbar. */
final class EmploymentContractTest extends TestCase {
    use RefreshDatabase;

    private const PDF = "%PDF-1.4\n1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n2 0 obj\n<< /Type /Pages /Kids [] /Count 0 >>\nendobj\ntrailer\n<< /Root 1 0 R >>\n%%EOF\n";

    private Organization $org;

    private User $hr;

    private User $member;

    protected function setUp(): void {
        parent::setUp();
        Storage::fake('local');
        Mail::fake();
        $this->org = Organization::factory()->create();
        app()->instance('currentOrganization', $this->org);
        $this->member = User::factory()->user()->create(['organization_id' => $this->org->id, 'name' => 'Erika Beispiel', 'email' => 'erika@example.test']);
        $this->hr = User::factory()->user()->create(['organization_id' => $this->org->id, 'name' => 'Paula Personal']);
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->org->id);
        $this->hr->assignRole(PersonnelFilePermissions::ROLE_PERSONALAKTE);
    }

    protected function tearDown(): void {
        app(PermissionRegistrar::class)->setPermissionsTeamId(null);
        parent::tearDown();
    }

    public function test_contract_is_signed_and_filed_but_hidden_from_others(): void {
        $this->actingAs($this->hr)->get(route('contracts.employment.member.create', $this->member))->assertOk();
        $this->actingAs($this->hr)->post(route('contracts.employment.member.store', $this->member), [
            'title' => 'Arbeitsvertrag Erika', 'starts_on' => '2026-11-01', 'email' => 'erika@example.test',
            'declaration_text' => 'Ich stimme zu.', 'file' => UploadedFile::fake()->createWithContent('vertrag.pdf', self::PDF),
        ])->assertRedirect();

        $contract = Contract::query()->withoutGlobalScopes()->sole();
        $this->assertSame(ContractKind::Employment, $contract->kind);
        $this->assertSame($this->member->id, $contract->employee_user_id);
        $token = null;
        Mail::assertSent(AgreementLinkMail::class, function (AgreementLinkMail $mail) use (&$token): bool {
            $token = basename((string) parse_url($mail->url, PHP_URL_PATH));

            return $mail->hasTo('erika@example.test');
        });

        // Andere sehen den Arbeitsvertrag weder in der Liste noch direkt, auch nicht als Admin ohne Personalrolle.
        $admin = User::factory()->admin()->create(['organization_id' => $this->org->id]);
        $this->actingAs($admin)->get(route('contracts.index'))->assertOk()->assertDontSee('Arbeitsvertrag Erika');
        $this->actingAs($admin)->get(route('contracts.show', $contract))->assertNotFound();
        $this->assertSame(0, Document::query()->withoutGlobalScopes()->visibleTo($admin)->where('title', 'Arbeitsvertrag Erika')->count());
        $this->actingAs($this->hr)->get(route('contracts.show', $contract))->assertOk()->assertSee('Arbeitsvertrag Erika');

        auth()->logout();
        app()->forgetInstance('currentOrganization');
        $this->get(route('agreements.public-sign', ['token' => $token]))->assertOk()->assertSee('Arbeitnehmerseite')->assertSee('Erika Beispiel');
        $this->post(route('agreements.public-sign.submit', ['token' => $token]), [
            'signer_name' => 'Erika Beispiel', 'signature_method' => 'typed', 'typed_name' => 'Erika Beispiel',
            'declaration_accepted' => '1', 'authority_confirmed' => '1',
        ])->assertRedirect(route('agreements.public-thanks'));

        $revision = $contract->signingRevisions()->sole();
        $orgRequest = $revision->organizationRequest()->firstOrFail();
        app()->instance('currentOrganization', $this->org);
        $this->actingAs($this->hr)->post(route('contracts.signing.requests.countersign.store', $orgRequest), [
            'signer_name' => 'Paula Personal', 'signature_method' => 'typed', 'typed_name' => 'Paula Personal',
            'declaration_accepted' => '1', 'authority_confirmed' => '1',
        ])->assertRedirect();

        $this->assertSame(SigningRevisionStatus::Signed, $revision->fresh()?->status);
        $filed = Document::query()->withoutGlobalScopes()->where('documentable_type', $this->member->getMorphClass())->where('documentable_id', $this->member->id)->sole();
        $this->assertSame(HrDocumentCategory::Contract->value, $filed->hr_category instanceof \BackedEnum ? $filed->hr_category->value : $filed->hr_category);
    }

    public function test_general_contract_form_rejects_employment_and_needs_hr(): void {
        $user = User::factory()->user()->create(['organization_id' => $this->org->id]);
        $this->actingAs($user)->get(route('contracts.employment.member.create', $this->member))->assertForbidden();
    }
}
