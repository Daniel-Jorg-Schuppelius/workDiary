<?php
/*
 * Created on   : Thu Sep 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ProviderInvoiceImport.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Reselling\Register;

use App\Enums\Reselling\SubscriptionProvider;
use App\Models\{Organization, User};
use App\Services\Reselling\Marketplace\{ProviderInvoice, QualityHostingInvoiceReader};
use CommonToolkit\Enums\CurrencyCode;
use CommonToolkit\ValueObjects\Money;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * Anbieterrechnungen als PDF importieren (Feature 152, MVP-762): je Datei
 * lesen, zuteilen, eine Zusammenfassungszeile bilden. Zeilenbefunde des
 * Readers und die Summenprüfung des Allocators (`issues`) werden gesammelt,
 * nicht verschluckt — der Import läuft trotzdem durch (Review 2026-09-10,
 * B17). Fehlertexte: nur die übersetzten `RuntimeException`s der Reader
 * erreichen den Nutzer; alles andere wird protokolliert und generisch
 * gemeldet (C9 — kein Serverpfad, kein Toolkit-Text im UI). Nicht final:
 * der Reader ist final, Tests ersetzen daher `read()` (Text-Fixture statt PDF).
 */
class ProviderInvoiceImport {
    public function __construct(
        protected readonly PurchaseAllocator $allocator,
        protected readonly QualityHostingInvoiceReader $reader,
    ) {}

    /**
     * @param  list<UploadedFile>  $uploads
     * @return array{summary: list<string>, issues: list<string>, failed: bool, imported: int}
     */
    public function run(Organization $organization, array $uploads, ?User $user, SubscriptionProvider $provider = SubscriptionProvider::QualityHosting, CurrencyCode $currency = CurrencyCode::Euro): array {
        $summary = [];
        $issues = [];
        $failed = false;
        $imported = 0;
        foreach ($uploads as $upload) {
            $name = (string) $upload->getClientOriginalName();
            try {
                $invoice = $this->read($upload->getRealPath() ?: $upload->getPathname());
                if ($invoice->number === '' || $invoice->lines === []) {
                    $failed = true;
                    $summary[] = (string) __('resale.purchase.import.unreadable', ['file' => $name]);

                    continue;
                }
                $result = $this->allocator->importProviderInvoice($organization, $invoice, $provider, $user, $name, $currency);
                $imported++;
                $summary[] = (string) __('resale.purchase_issues.result', ['number' => $invoice->number, 'matched' => $result['matched'], 'lines' => $result['lines'], 'duplicates' => $result['duplicates'], 'net' => Money::ofFloat($result['net'], $currency, 2)->format()])
                    . ($result['unmatched'] !== [] ? ' ' . __('resale.purchase.import.unmatched', ['list' => implode('; ', array_slice($result['unmatched'], 0, 5))]) : '');
                foreach ($result['issues'] as $issue) {
                    $issues[] = (string) __('resale.purchase_issues.line', ['file' => $name, 'issue' => $issue]);
                }
            } catch (Throwable $e) {
                $failed = true;
                $summary[] = self::userMessage($e, $name);
            }
        }

        return ['summary' => $summary, 'issues' => $issues, 'failed' => $failed, 'imported' => $imported];
    }

    /** PDF lesen — einzige Naht zum Reader (Tests liefern hier ein Text-Fixture). */
    protected function read(string $path): ProviderInvoice {
        return $this->reader->read($path);
    }

    /**
     * Übersetzte Reader-Fehler (genau `RuntimeException`) durchreichen, alle
     * anderen Ausnahmen protokollieren und generisch melden.
     */
    private static function userMessage(Throwable $e, string $file): string {
        if ($e::class === RuntimeException::class) {
            return $e->getMessage();
        }
        Log::warning('resale: Anbieterrechnung nicht importiert', ['file' => $file, 'exception' => $e::class, 'message' => $e->getMessage()]);

        return (string) __('resale.purchase_issues.failed', ['file' => $file]);
    }
}
