<?php
/*
 * Created on   : Thu Aug 13 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MemberImportTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Org;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * MVP-537 (Feature 103, Q1-Drittabgleich): Personalstamm-CSV-Import —
 * Vorlagen-Defaults, Übersprungen-Gründe, Berechtigung.
 */
class MemberImportTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->admin = $this->orgAdmin();
    }

    private function csvFile(string $content): UploadedFile {
        return UploadedFile::fake()->createWithContent('personal.csv', $content);
    }

    public function test_import_creates_users_with_template_defaults(): void {
        $csv = "name;email;personnel_number;role\n"
            . "Erika Import;erika@example.com;2001;user\n"
            . "Bodo Buchhalter;bodo@example.com;;buchhaltung\n";

        $this->actingAs($this->admin)
            ->post(route('org.members.import'), ['csv' => $this->csvFile($csv)])
            ->assertRedirect(route('org.members.index'))
            ->assertSessionHas('success');

        $erika = User::query()->where('email', 'erika@example.com')->firstOrFail();
        $this->assertSame((int) $this->organization->id, (int) $erika->organization_id);
        $this->assertSame('2001', $erika->personnel_number);
        $this->assertTrue((bool) $erika->must_change_password);
        $this->assertTrue((bool) $erika->is_new_system);
        $this->assertTrue($erika->hasRole('user'));

        $bodo = User::query()->where('email', 'bodo@example.com')->firstOrFail();
        $this->assertTrue($bodo->hasRole('buchhaltung'));
    }

    public function test_invalid_rows_and_existing_emails_are_skipped_with_reason(): void {
        $existing = $this->orgUser();
        $csv = "name;email\n"
            . "Ohne Mail;\n"
            . "Doppelt;{$existing->email}\n"
            . "Rolle Falsch;neu@example.com\n";
        // dritte Zeile gültig (Default-Rolle user)

        $this->actingAs($this->admin)
            ->post(route('org.members.import'), ['csv' => $this->csvFile($csv)])
            ->assertRedirect();

        $this->assertDatabaseHas('users', ['email' => 'neu@example.com']);
        $this->assertDatabaseMissing('users', ['name' => 'Ohne Mail']);
        $this->assertSame(1, User::query()->where('email', $existing->email)->count());
    }

    public function test_header_without_email_column_is_rejected(): void {
        $this->actingAs($this->admin)
            ->post(route('org.members.import'), ['csv' => $this->csvFile("name;irgendwas\nA;B\n")])
            ->assertSessionHasErrors('csv');
    }

    public function test_requires_manage_members_permission(): void {
        $plain = $this->orgUser();

        $this->actingAs($plain)
            ->post(route('org.members.import'), ['csv' => $this->csvFile("name;email\nX;x@example.com\n")])
            ->assertForbidden();
    }

    public function test_template_download(): void {
        $response = $this->actingAs($this->admin)->get(route('org.members.import.template'));

        $response->assertOk();
        $this->assertStringContainsString('name;email', (string) $response->getContent());
    }
}
