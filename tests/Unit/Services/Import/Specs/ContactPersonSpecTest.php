<?php
/*
 * Created on   : Tue Aug 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ContactPersonSpecTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Unit\Services\Import\Specs;

use App\Enums\Import\ImportErrorCode;
use App\Models\{Customer, Supplier};
use App\Services\Import\ImportOutcome;
use App\Services\Import\Specs\ContactPersonSpec;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

class ContactPersonSpecTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private Customer $customer;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->customer = Customer::factory()->create(['organization_id' => $this->organization->id, 'number' => 'K-1', 'name' => 'Kunde']);
    }

    public function test_normalize_maps_party_type_email_and_primary(): void {
        $row = (new ContactPersonSpec())->normalize([
            'party_type' => 'Lieferant',
            'party_number' => 'L-1',
            'name' => ' Erika ',
            'email' => 'Erika@Example.COM',
            'primary' => 'ja',
        ]);

        $this->assertSame(ContactPersonSpec::PARTY_SUPPLIER, $row['party_type']);
        $this->assertSame('Erika', $row['name']);
        $this->assertSame('erika@example.com', $row['email']);
        $this->assertTrue($row['primary']);
    }

    public function test_validate_row_reports_unknown_party_and_bad_email(): void {
        $spec = new ContactPersonSpec();

        $issues = $spec->validateRow($spec->normalize(['party_number' => 'K-9', 'name' => 'X', 'email' => 'nope']), $this->organization);

        $codes = array_map(static fn($i) => $i->code, $issues);
        $this->assertContains(ImportErrorCode::FkMissing, $codes);
        $this->assertContains(ImportErrorCode::Format, $codes);
    }

    public function test_upsert_appends_matches_by_email_then_name_and_handles_primary(): void {
        $spec = new ContactPersonSpec();

        [$o1] = $spec->upsert($spec->normalize(['party_number' => 'K-1', 'name' => 'Erika Muster', 'email' => 'erika@example.com']), $this->organization);
        [$o2] = $spec->upsert($spec->normalize(['party_number' => 'K-1', 'name' => 'Max Muster', 'phone' => '0123', 'primary' => 'ja']), $this->organization);
        // Gleiche E-Mail, anderer Name → Update statt Dublette.
        [$o3] = $spec->upsert($spec->normalize(['party_number' => 'K-1', 'name' => 'Erika Mustermann', 'email' => 'ERIKA@example.com', 'phone' => '0999']), $this->organization);
        // Gleicher Name ohne E-Mail → Update über den Namen.
        [$o4] = $spec->upsert($spec->normalize(['party_number' => 'K-1', 'name' => 'max muster', 'email' => 'max@example.com']), $this->organization);

        $this->assertSame([ImportOutcome::Created, ImportOutcome::Created, ImportOutcome::Updated, ImportOutcome::Updated], [$o1, $o2, $o3, $o4]);

        $persons = $this->customer->fresh()?->contact_persons ?? [];
        $this->assertCount(2, $persons);
        $this->assertSame('Erika Mustermann', $persons[0]['name']);
        $this->assertSame('0999', $persons[0]['phone']);
        $this->assertFalse($persons[0]['primary'], 'Erster Kontakt verliert Hauptkontakt an Max');
        $this->assertSame('Max Muster', $persons[1]['name']);
        $this->assertSame('max@example.com', $persons[1]['email']);
        $this->assertTrue($persons[1]['primary']);
        $this->assertSame('Max Muster', $this->customer->fresh()?->primaryContact()['name']);
    }

    public function test_supplier_party_and_person_cap(): void {
        $supplier = Supplier::factory()->create(['organization_id' => $this->organization->id, 'number' => 'L-1']);
        $supplier->forceFill(['contact_persons' => array_map(static fn(int $i): array => ['name' => "P{$i}"], range(1, ContactPersonSpec::MAX_PERSONS))])->save();
        $spec = new ContactPersonSpec();

        [$ok] = $spec->upsert($spec->normalize(['party_type' => 'supplier', 'party_number' => 'L-1', 'name' => 'P1', 'email' => 'p1@example.com']), $this->organization);
        [$full, $issue] = $spec->upsert($spec->normalize(['party_type' => 'supplier', 'party_number' => 'L-1', 'name' => 'Neu']), $this->organization);

        $this->assertSame(ImportOutcome::Updated, $ok);
        $this->assertSame(ImportOutcome::Failed, $full);
        $this->assertSame(ImportErrorCode::OutOfRange, $issue?->code);
        $this->assertCount(ContactPersonSpec::MAX_PERSONS, $supplier->fresh()?->contact_persons ?? []);
    }
}
