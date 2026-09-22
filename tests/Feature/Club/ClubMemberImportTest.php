<?php
/*
 * Created on   : Tue Sep 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ClubMemberImportTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Club;

use App\Models\Club\ClubMember;
use App\Services\Import\ImportOutcome;
use App\Services\Import\Specs\ClubMemberSpec;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * CSV-Erstimport von Vereinsmitgliedern (MVP-842): Mitgliedsnummer ist der
 * Abgleichschlüssel, Familien-E-Mail keiner; Pflichtfelder und Formate
 * landen in der Fehlerliste statt in halben Datensätzen.
 */
class ClubMemberImportTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
    }

    private function spec(): ClubMemberSpec {
        return app(ClubMemberSpec::class);
    }

    public function test_import_creates_by_member_no_and_updates_on_repeat(): void {
        $spec = $this->spec();
        $row = $spec->normalize(['member_no' => '12', 'first_name' => 'Mia', 'last_name' => 'Muster', 'email' => 'Familie@Example.test', 'birth_date' => '04.03.2018', 'kind' => 'passiv', 'joined_on' => '2026-01-15']);
        $this->assertSame([], $spec->validateRow($row, $this->organization));
        $this->assertSame('passive', $row['kind']);
        $this->assertSame('2018-03-04', $row['birth_date']);

        [$outcome] = $spec->upsert($row, $this->organization);
        $this->assertSame(ImportOutcome::Created, $outcome);

        $again = $spec->normalize(['member_no' => '12', 'first_name' => 'Mia', 'last_name' => 'Muster-Neu']);
        [$outcome] = $spec->upsert($again, $this->organization);
        $this->assertSame(ImportOutcome::Updated, $outcome);

        $this->assertSame(1, ClubMember::query()->count());
        $member = ClubMember::query()->firstOrFail();
        $this->assertSame(12, $member->member_no);
        $this->assertSame('Muster-Neu', $member->last_name);
        $this->assertSame('familie@example.test', $member->email);
        $this->assertSame('passive', $member->kind->value);
        $this->assertSame('2026-01-15', $member->joined_on->toDateString());
    }

    public function test_shared_family_email_is_not_a_duplicate_key(): void {
        $spec = $this->spec();
        $spec->upsert($spec->normalize(['first_name' => 'Mia', 'last_name' => 'Muster', 'email' => 'familie@example.test']), $this->organization);
        $spec->upsert($spec->normalize(['first_name' => 'Ben', 'last_name' => 'Muster', 'email' => 'familie@example.test']), $this->organization);

        $this->assertSame(2, ClubMember::query()->count());
        $this->assertSame([1, 2], ClubMember::query()->orderBy('member_no')->pluck('member_no')->all());
    }

    public function test_missing_required_fields_and_bad_formats_are_reported(): void {
        $spec = $this->spec();

        $issues = $spec->validateRow($spec->normalize(['first_name' => 'Mia', 'birth_date' => '31.02.2018', 'kind' => 'unbekannt', 'member_no' => 'abc']), $this->organization);
        $fields = array_map(static fn($issue): string => $issue->field, $issues);

        $this->assertContains('last_name', $fields);
        $this->assertContains('birth_date', $fields);
        $this->assertContains('kind', $fields);
        $this->assertContains('member_no', $fields);
        $this->assertSame(0, ClubMember::query()->count());
    }
}
