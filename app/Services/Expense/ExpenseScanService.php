<?php
/*
 * Created on   : Tue Aug 18 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ExpenseScanService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Expense;

use App\Models\{Expense, Organization, User};
use App\Services\Invoicing\InvoicePdfImportService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

/**
 * Scan-Beleg → Auslagen-Vorschlag (Feature 088 P3, MVP-669).
 *
 * Der Scan erzeugt eine **Entwurfs-Auslage** mit den extrahierten Werten
 * (Datum, Brutto, USt, Händler) und dem Beleg als Anhang — **der Mensch
 * bestätigt** im Formular, nie eine Auto-Buchung. Die Extraktion läuft über
 * denselben Weg wie der Rechnungsdatei-Import (Feature 104): Regel-Heuristik
 * zuerst, KI-Fallback nur für leere Kernfelder, ohne Provider exakt wie ohne
 * KI.
 *
 * Nimmt PDF-Scans (OCR über die PDFReaderRegistry) UND Handy-Fotos
 * (JPG/PNG/TIFF) — letztere über die Bild-Direkt-OCR des pdf-toolkits
 * (TesseractReader::extractTextFromImage, seit v0.15.6).
 */
class ExpenseScanService {
    public function __construct(
        private readonly InvoicePdfImportService $importService,
        private readonly ExpenseService $expenses,
    ) {}

    /**
     * @return array{expense: Expense, extracted: array<string, mixed>}
     */
    public function createDraftFromScan(UploadedFile $file, User $actor, Organization $organization): array {
        $extracted = $this->importService->extract(
            (string) $file->getRealPath(),
            strtolower($file->getClientOriginalExtension() ?: 'pdf'),
            $file->getMimeType(),
            $organization,
        );

        $gross = $this->decimal($extracted['gross'] ?? null);
        $tax = $this->decimal($extracted['tax'] ?? null);
        $net = $this->decimal($extracted['net'] ?? null) ?? ($gross !== null && $tax !== null ? round($gross - $tax, 2) : null);

        $expense = $this->expenses->create([
            'organization_id' => $organization->id,
            'user_id' => $actor->id,
            'date' => $extracted['issued_on'] ?? now()->toDateString(),
            // Die Text-Heuristik kennt keinen Absendernamen - nur der
            // strukturierte Weg liefert ihn; sonst füllt ihn der Mensch.
            'vendor' => is_array($extracted['seller'] ?? null) ? ($extracted['seller']['name'] ?? null) : null,
            'description' => (string) __('Scan-Beleg :name', ['name' => $file->getClientOriginalName()]),
            'payment_method' => 'private_paid',
            'currency' => (string) ($extracted['currency'] ?? 'EUR'),
            // Die Beträge sind NOT NULL - ein unlesbarer Scan ergibt einen
            // 0,00-Entwurf, den der Mensch ohnehin füllt.
            'amount_net' => $net ?? 0.0,
            'tax_rate' => $this->decimal($extracted['tax_rate'] ?? null),
            'tax_amount' => $tax ?? 0.0,
            'amount_gross' => $gross ?? 0.0,
        ]);

        $this->attach($expense, $file, $actor);
        $expense->audit('expense.scanned', [
            'ocr_used' => (bool) ($extracted['ocr_used'] ?? false),
            'reader' => $extracted['reader'] ?? null,
        ]);

        return ['expense' => $expense, 'extracted' => $extracted];
    }

    private function attach(Expense $expense, UploadedFile $file, User $actor): void {
        $disk = 'local';
        $folder = 'expenses/' . $expense->organization_id . '/' . $expense->id;
        $ext = strtolower($file->getClientOriginalExtension() ?: 'pdf');
        $path = $file->storeAs($folder, Str::uuid()->toString() . '.' . $ext, $disk);

        $expense->attachments()->create([
            'user_id' => $actor->id,
            'disk' => $disk,
            'path' => $path,
            'original_name' => Str::limit(preg_replace('/[^\w\-. ]+/u', '_', $file->getClientOriginalName()) ?: 'beleg.pdf', 120, ''),
            'mime' => $file->getMimeType() ?? 'application/pdf',
            'size' => $file->getSize() ?: 0,
        ]);
    }

    private function decimal(mixed $value): ?float {
        if ($value === null || $value === '') {
            return null;
        }

        return is_numeric($value) ? round((float) $value, 2) : null;
    }
}
