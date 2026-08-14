<?php
/*
 * Created on   : Tue May 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ImportControllerTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\Import\{ImportEntity, ImportRunState};
use App\Jobs\ProcessCsvImportJob;
use App\Models\{Customer, ImportRun, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\{Queue, Storage};
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

class ImportControllerTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        Storage::fake('local');
    }

    public function test_non_admin_cannot_open_create_form(): void {
        $user = User::factory()->user()->create(['organization_id' => $this->organization->id]);

        $this->actingAs($user)
            ->get(route('admin.imports.create', ['entity' => 'customers']))
            ->assertForbidden();
    }

    /** Vollaudit 2026-07 (N8): CSV-Mustervorlage je Entität aus Spec::columns(). */
    public function test_template_download_contains_headers_and_example_row(): void {
        $admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);

        $response = $this->actingAs($admin)
            ->get(route('admin.imports.template', ['entity' => 'customers']))
            ->assertOk()
            ->assertHeader('Content-Type', 'text/csv; charset=UTF-8');

        $csv = (string) $response->getContent();
        $this->assertStringContainsString('name', $csv);
        $this->assertStringContainsString(__('import.template.example_required'), $csv);

        $this->actingAs($admin)
            ->get(route('admin.imports.template', ['entity' => 'gibts-nicht']))
            ->assertNotFound();
    }

    public function test_admin_sees_index_with_runs(): void {
        $admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);

        ImportRun::create([
            'organization_id' => $this->organization->id,
            'entity' => ImportEntity::Customers,
            'state' => ImportRunState::AwaitingApproval,
            'input_filename' => 'a.csv',
            'input_hash' => str_repeat('a', 64),
            'storage_path' => 'imports/' . $this->organization->id . '/a.csv',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.imports.index'))
            ->assertOk()
            ->assertSee('a.csv');
    }

    public function test_preflight_creates_awaiting_run_for_valid_csv(): void {
        $admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);

        $csv = "name;number;currency\nACME;K-1;EUR\nFoo;K-2;EUR\n";
        $file = UploadedFile::fake()->createWithContent('customers.csv', $csv);

        $response = $this->actingAs($admin)
            ->post(route('admin.imports.preflight'), [
                'entity' => 'customers',
                'file' => $file,
            ]);

        $run = ImportRun::query()->latest('id')->firstOrFail();
        $response->assertRedirect(route('admin.imports.show', $run));
        $this->assertSame(ImportRunState::AwaitingApproval, $run->state);
        $this->assertSame(ImportEntity::Customers, $run->entity);
        $this->assertSame('customers.csv', $run->input_filename);
        $this->assertTrue(Storage::disk('local')->exists($run->storage_path));
    }

    public function test_preflight_marks_failed_when_required_header_missing(): void {
        $admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);

        $csv = "number;currency\nK-1;EUR\n";
        $file = UploadedFile::fake()->createWithContent('bad.csv', $csv);

        $this->actingAs($admin)
            ->post(route('admin.imports.preflight'), [
                'entity' => 'customers',
                'file' => $file,
            ])->assertRedirect();

        $run = ImportRun::query()->latest('id')->firstOrFail();
        $this->assertSame(ImportRunState::Failed, $run->state);
        $this->assertGreaterThan(0, $run->errors()->count());
    }

    public function test_confirm_dispatches_job_only_for_awaiting_state(): void {
        Queue::fake();
        $admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);

        $run = ImportRun::create([
            'organization_id' => $this->organization->id,
            'entity' => ImportEntity::Customers,
            'state' => ImportRunState::AwaitingApproval,
            'input_filename' => 'a.csv',
            'input_hash' => str_repeat('a', 64),
            'storage_path' => 'imports/' . $this->organization->id . '/a.csv',
        ]);

        $this->actingAs($admin)
            ->post(route('admin.imports.confirm', $run))
            ->assertRedirect(route('admin.imports.show', $run));

        Queue::assertPushed(ProcessCsvImportJob::class);
    }

    public function test_confirm_blocks_when_state_not_awaiting(): void {
        Queue::fake();
        $admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);

        $run = ImportRun::create([
            'organization_id' => $this->organization->id,
            'entity' => ImportEntity::Customers,
            'state' => ImportRunState::Succeeded,
            'input_filename' => 'a.csv',
            'input_hash' => str_repeat('a', 64),
            'storage_path' => 'imports/' . $this->organization->id . '/a.csv',
        ]);

        $this->actingAs($admin)
            ->post(route('admin.imports.confirm', $run))
            ->assertStatus(409);

        Queue::assertNothingPushed();
    }

    public function test_destroy_removes_run_and_file(): void {
        $admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);

        $path = 'imports/' . $this->organization->id . '/to-delete.csv';
        Storage::disk('local')->put($path, 'name;number;currency\nA;1;EUR');

        $run = ImportRun::create([
            'organization_id' => $this->organization->id,
            'entity' => ImportEntity::Customers,
            'state' => ImportRunState::AwaitingApproval,
            'input_filename' => 'to-delete.csv',
            'input_hash' => str_repeat('b', 64),
            'storage_path' => $path,
        ]);

        $this->actingAs($admin)
            ->delete(route('admin.imports.destroy', $run))
            ->assertRedirect(route('admin.imports.index'));

        $this->assertNull(ImportRun::find($run->id));
        $this->assertFalse(Storage::disk('local')->exists($path));
    }

    public function test_full_pipeline_creates_customers(): void {
        $admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);

        $csv = "name;number;currency\nACME;K-100;EUR\nFoo;K-200;EUR\n";
        $file = UploadedFile::fake()->createWithContent('customers.csv', $csv);

        $this->actingAs($admin)
            ->post(route('admin.imports.preflight'), [
                'entity' => 'customers',
                'file' => $file,
            ]);

        $run = ImportRun::query()->latest('id')->firstOrFail();
        $this->assertSame(ImportRunState::AwaitingApproval, $run->state);

        // Run job synchronously to validate end-to-end behaviour.
        $this->actingAs($admin)
            ->post(route('admin.imports.confirm', $run))
            ->assertRedirect();

        $run->refresh();
        $this->assertContains($run->state, [ImportRunState::Succeeded, ImportRunState::Running]);
        $this->assertSame(2, Customer::query()
            ->where('organization_id', $this->organization->id)
            ->whereIn('number', ['K-100', 'K-200'])
            ->count());
    }

    // ── A13: XLSX-Annahme ─────────────────────────────────────────────────────

    /**
     * Baut eine XLSX-Fixture über die Toolkit-Writer (Builder + Generator).
     * Seit php-common-toolkit v1.15.8 schreibt der Generator Datumszellen
     * korrekt (eigener Datums-cellXf, `dateTimeToExcel` ohne doppelte
     * Lotus-Korrektur) — die Fixture schreibt das echte Datum ohne die
     * früheren Kompensationen (Datum −1 Tag, styles.xml-Nachrüstung).
     *
     * @param  list<string>  $header
     * @param  list<list<mixed>>  $rows
     */
    private function makeXlsxUpload(array $header, array $rows, string $filename = 'customers.xlsx'): UploadedFile {
        $builder = (new \CommonToolkit\Builders\XLSXDocumentBuilder)->sheet('Tabelle1')->setHeader($header);
        foreach ($rows as $row) {
            $builder->addRow($row);
        }

        $path = tempnam(sys_get_temp_dir(), 'a13_') . '.xlsx';
        \CommonToolkit\Generators\XLSX\XLSXGenerator::toFile($builder->build(), $path);

        return new UploadedFile($path, $filename, \App\Support\XlsxExport::MIME, null, true);
    }

    public function test_xlsx_preflight_produces_same_preview_and_import_as_csv(): void {
        $admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);

        // Äquivalente Inhalte: Text, Nummer, Dezimalzahl (Float-Zelle) und Datum (Datumszelle).
        $csv = "name;number;hourly_rate;comment\nACME;K-1;25.5;2026-07-01\nFoo;K-2;100;Text\n";
        $this->actingAs($admin)->post(route('admin.imports.preflight'), [
            'entity' => 'customers',
            'file' => UploadedFile::fake()->createWithContent('customers.csv', $csv),
        ])->assertRedirect();
        $csvRun = ImportRun::query()->latest('id')->firstOrFail();

        $xlsx = $this->makeXlsxUpload(
            ['name', 'number', 'hourly_rate', 'comment'],
            [
                ['ACME', 'K-1', 25.5, new \DateTimeImmutable('2026-07-01')],
                ['Foo', 'K-2', 100, 'Text'],
            ],
        );
        $this->actingAs($admin)->post(route('admin.imports.preflight'), [
            'entity' => 'customers',
            'file' => $xlsx,
        ])->assertRedirect();
        $xlsxRun = ImportRun::query()->latest('id')->firstOrFail();

        $this->assertNotSame($csvRun->id, $xlsxRun->id);
        $this->assertSame(ImportRunState::AwaitingApproval, $xlsxRun->state);
        $this->assertSame('customers.xlsx', $xlsxRun->input_filename);
        // Nach der Überführung liegt intern EINE CSV-Struktur (ein Wizard-Pfad).
        $this->assertTrue(str_ends_with($xlsxRun->storage_path, '.csv'));
        $this->assertTrue(Storage::disk('local')->exists($xlsxRun->storage_path));

        // Gleiche Vorschau wie das äquivalente CSV.
        $this->assertSame($csvRun->preview, $xlsxRun->preview);

        // Gleiches Import-Ergebnis.
        $this->actingAs($admin)->post(route('admin.imports.confirm', $xlsxRun))->assertRedirect();
        $customer = Customer::query()->where('number', 'K-1')->firstOrFail();
        $this->assertSame('ACME', $customer->name);
        $this->assertSame('2026-07-01', $customer->comment);
        $this->assertEqualsWithDelta(25.5, $customer->hourly_rate?->toFloat(), 0.001);
    }

    public function test_corrupt_xlsx_fails_preflight_with_readable_error(): void {
        $admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);

        $file = UploadedFile::fake()->createWithContent('broken.xlsx', 'kein gültiges zip-archiv');

        $this->actingAs($admin)->post(route('admin.imports.preflight'), [
            'entity' => 'customers',
            'file' => $file,
        ])->assertRedirect(); // kein 500

        $run = ImportRun::query()->latest('id')->firstOrFail();
        $this->assertSame(ImportRunState::Failed, $run->state);
        $this->assertTrue(
            $run->errors()->get()->contains(
                fn ($err) => str_contains((string) $err->message, (string) __('import.error.format.xlsxUnreadable')),
            ),
            'Erwartete verständliche XLSX-Fehlermeldung fehlt.',
        );
    }

    public function test_size_limit_applies_to_xlsx_upload(): void {
        $admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);

        // 6 MB > 5-MB-Limit des Wizards.
        $file = UploadedFile::fake()->create('big.xlsx', 6 * 1024, \App\Support\XlsxExport::MIME);

        $this->actingAs($admin)->post(route('admin.imports.preflight'), [
            'entity' => 'customers',
            'file' => $file,
        ])->assertSessionHasErrors('file');

        $this->assertSame(0, ImportRun::query()->count());
    }
}
