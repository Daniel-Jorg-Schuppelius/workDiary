<?php
/*
 * Created on   : Sat Aug 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ArticleExportController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesCurrentOrganization;
use App\Models\Article;
use App\Services\Procurement\DatanormExportService;
use ERechnungToolkit\Enums\{DatanormPriceIndicator, DatanormVersion};
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\Gate;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use ZipArchive;

/**
 * DATANORM-Export des Artikelstamms (Feature 107, W5): liefert ein ZIP mit
 * DATANORM.001 + DATAINFO.TXT in Version 4 oder 5, Preisquelle VK als
 * Listen- oder Nettopreis. Empfänger sind die Kunden der Organisation
 * (Handwerker-/Warenwirtschaftssoftware).
 */
class ArticleExportController extends Controller {
    use ResolvesCurrentOrganization;

    public function datanorm(Request $request, DatanormExportService $export): BinaryFileResponse|RedirectResponse {
        Gate::authorize('viewAny', Article::class);
        $request->validate([
            'version' => ['nullable', 'in:4,5'],
            'prices' => ['nullable', 'in:list,net'],
            'type' => ['nullable', 'in:catalog,prices'],
            'since_days' => ['nullable', 'integer', 'min:1', 'max:365'],
            'since' => ['nullable', 'date', 'before_or_equal:today'],
        ]);

        $version = $request->input('version') === '4' ? DatanormVersion::V4 : DatanormVersion::V5;
        $priceIndicator = $request->input('prices') === 'net'
            ? DatanormPriceIndicator::NetPrice
            : DatanormPriceIndicator::ListPrice;
        $isPriceFile = $request->input('type') === 'prices';
        // W10/MVP-566: DATPREIS nur mit VK-Änderungen seit Datum bzw. X Tagen.
        $since = null;
        if ($request->filled('since')) {
            $since = \Illuminate\Support\Carbon::parse((string) $request->input('since'))->startOfDay();
        } elseif ($request->filled('since_days')) {
            $since = now()->subDays((int) $request->input('since_days'))->startOfDay();
        }

        $result = $isPriceFile
            ? $export->exportPrices($this->currentOrganization(), $version, $priceIndicator, null, $since)
            : $export->export($this->currentOrganization(), $version, $priceIndicator);
        if ($result['articles'] === 0) {
            return back()->with('error', (string) __('article.flash.datanorm_empty'));
        }

        // Preislisten-Abfluss ist auditpflichtig (Muster der Report-Exporte).
        \App\Models\AuditLog::create([
            'organization_id' => $this->currentOrganization()->id,
            'user_id' => $request->user()?->id,
            'event' => 'datanorm.exported',
            'auditable_type' => self::class,
            'auditable_id' => 0,
            'changes' => [
                'type' => $isPriceFile ? 'prices' : 'catalog',
                'version' => $version->value,
                'price_indicator' => $priceIndicator->value,
                'articles' => $result['articles'],
                'skipped' => count($result['skipped']),
            ],
            'ip' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 255),
        ]);

        return $this->zipResponse($result['files'], ($isPriceFile ? 'datpreis' : 'datanorm') . '-v' . ($version === DatanormVersion::V4 ? '4' : '5') . '.zip');
    }

    /**
     * @param  array<string, string>  $files
     */
    public static function buildZipResponse(array $files, string $downloadName): BinaryFileResponse {
        $path = tempnam(sys_get_temp_dir(), 'datanorm');
        if ($path === false) {
            throw new RuntimeException('Failed to create temporary export file.');
        }
        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::OVERWRITE);
        foreach ($files as $name => $bytes) {
            $zip->addFromString($name, $bytes);
        }
        $zip->close();

        return response()->download($path, $downloadName, [
            'Content-Type' => 'application/zip',
        ])->deleteFileAfterSend(true);
    }

    /**
     * @param  array<string, string>  $files
     */
    private function zipResponse(array $files, string $downloadName): BinaryFileResponse {
        return self::buildZipResponse($files, $downloadName);
    }
}
