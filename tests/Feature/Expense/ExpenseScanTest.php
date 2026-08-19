<?php
/*
 * Created on   : Tue Aug 18 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ExpenseScanTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Expense;

use App\Enums\Expense\ExpenseStatus;
use App\Models\{Expense, User};
use App\Services\Expense\{ExpenseScanService, ExpenseService};
use App\Services\Invoicing\InvoicePdfImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Scan-Beleg → Auslagen-Vorschlag (Feature 088 P3, MVP-669).
 *
 * Kern der Prüfung: Der Scan erzeugt einen **Entwurf** (nie mehr) mit den
 * extrahierten Werten und dem Beleg als Anhang — Kategorie und Händler
 * ergänzt der Mensch.
 */
final class ExpenseScanTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        Storage::fake('local');
    }

    /** Extraktion gemockt: Es geht um den Vorschlags-Fluss, nicht um OCR. */
    private function fakeExtraction(array $extracted): void {
        $mock = $this->createMock(InvoicePdfImportService::class);
        $mock->method('extract')->willReturn($extracted);
        $this->app->instance(InvoicePdfImportService::class, $mock);
    }

    public function test_scan_creates_a_draft_with_extracted_values_and_attachment(): void {
        $this->fakeExtraction([
            'issued_on' => '2026-08-10',
            'gross' => '59.50',
            'tax' => '9.50',
            'net' => '50.00',
            'tax_rate' => '19.00',
            'currency' => 'EUR',
            'ocr_used' => true,
            'reader' => 'tesseract',
        ]);

        $service = new ExpenseScanService(app(InvoicePdfImportService::class), app(ExpenseService::class));
        $result = $service->createDraftFromScan(
            UploadedFile::fake()->create('tankbeleg.pdf', 100, 'application/pdf'),
            $this->admin,
            $this->organization,
        );

        $expense = $result['expense'];
        // Ein ENTWURF - nie mehr: der Mensch bestätigt.
        $this->assertSame(ExpenseStatus::Draft, $expense->status);
        $this->assertSame('59.50', (string) $expense->amount_gross?->getAmount());
        $this->assertSame('2026-08-10', $expense->date->toDateString());
        $this->assertSame(1, $expense->attachments()->count());
    }

    /** Ohne extrahierbare Werte bleibt der Entwurf leer, entsteht aber. */
    public function test_scan_without_values_still_creates_an_empty_draft(): void {
        $this->fakeExtraction(['ocr_used' => false]);

        $service = new ExpenseScanService(app(InvoicePdfImportService::class), app(ExpenseService::class));
        $result = $service->createDraftFromScan(
            UploadedFile::fake()->create('unleserlich.pdf', 50, 'application/pdf'),
            $this->admin,
            $this->organization,
        );

        $this->assertSame(ExpenseStatus::Draft, $result['expense']->status);
        // Unlesbarer Scan → 0,00-Entwurf (Beträge sind NOT NULL).
        $this->assertSame('0.00', (string) $result['expense']->amount_gross?->getAmount());
    }

    public function test_scan_endpoint_redirects_to_the_edit_form(): void {
        $this->fakeExtraction(['gross' => '10.00', 'currency' => 'EUR']);

        $response = $this->actingAs($this->admin)->post(route('expenses.scan'), [
            'receipt' => UploadedFile::fake()->create('bon.pdf', 20, 'application/pdf'),
        ]);

        $expense = Expense::query()->firstOrFail();
        $response->assertRedirect(route('expenses.edit', $expense));
    }

    /** PDF und Fotos ja — beliebige Dateitypen nein. */
    public function test_unsupported_upload_is_rejected(): void {
        $this->actingAs($this->admin)->post(route('expenses.scan'), [
            'receipt' => UploadedFile::fake()->create('beleg.txt', 5, 'text/plain'),
        ])->assertSessionHasErrors('receipt');
    }

    /** Folgepunkt 088: Handy-Foto (PNG) läuft über die Bild-Direkt-OCR des pdf-toolkits. */
    public function test_photo_upload_creates_a_draft_via_image_ocr(): void {
        if (! function_exists('imagecreatetruecolor')) {
            $this->markTestSkipped('GD not available');
        }

        // Echte Extraktion (kein Mock): Ohne tesseract bleibt der Text leer und
        // es entsteht der dokumentierte 0,00-Entwurf - der Fluss ist derselbe.
        $file = UploadedFile::fake()->image('beleg.png', 600, 400);

        $this->actingAs($this->admin)
            ->post(route('expenses.scan'), ['receipt' => $file])
            ->assertRedirect();

        $expense = Expense::query()->firstOrFail();
        $this->assertSame(ExpenseStatus::Draft, $expense->status);
        $this->assertSame('image/png', $expense->attachments()->firstOrFail()->mime);
    }
}
