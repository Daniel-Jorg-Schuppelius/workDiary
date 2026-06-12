<?php
/*
 * Created on   : Thu Jun 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PublicAuditPackageController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers;

use App\Models\Isms\IsmsAuditPackage;
use App\Services\Isms\AuditPackageService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Öffentlicher Prüfer-Download finalisierter Auditpakete (Feature 046,
 * Inkrement E / 044 „optionaler zeitlich begrenzter, lesender
 * Prüferzugang"): token-basiert OHNE Login und ohne Org-Session (Muster
 * PublicProtocolSignatureController).
 *
 * Der Token wird über seinen SHA-256-Hash aufgelöst; widerrufene,
 * abgelaufene und unbekannte Tokens antworten einheitlich 404 (keine
 * Detail-Preisgabe). Jeder erfolgreiche Abruf setzt last_accessed_at.
 * Brute-Force-Schutz über throttle (Route).
 */
class PublicAuditPackageController extends Controller {
    public function __construct(private readonly AuditPackageService $packages) {}

    public function download(string $token): StreamedResponse {
        $record = $this->packages->resolveUsableToken($token);
        abort_if($record === null, 404);

        // Kein Org-Kontext gebunden ⇒ der OrganizationScope filtert nicht;
        // das Paket wird ausschließlich über den Token-Besitz aufgelöst.
        $package = IsmsAuditPackage::query()
            ->whereKey($record->isms_audit_package_id)
            ->first();
        abort_if($package === null || ! $package->isFinalized(), 404);

        $path = (string) $package->file_path;
        // Pfad-Härtung analog AuditPackageController::download().
        abort_unless($path !== '' && str_starts_with($path, AuditPackageService::BASE_PATH . '/'), 404);
        abort_if(str_contains($path, '..'), 404);

        $disk = Storage::disk(AuditPackageService::DISK);
        abort_unless($disk->exists($path), 404);

        $record->forceFill(['last_accessed_at' => Carbon::now()])->save();

        $stream = $disk->readStream($path);
        abort_if($stream === null, 404);

        $fileName = sprintf(
            'auditpaket-%s-%s.json',
            $package->displayNo(),
            $package->as_of_date->format('Y-m-d'),
        );

        return response()->streamDownload(static function () use ($stream): void {
            fpassthru($stream);
            fclose($stream);
        }, $fileName, ['Content-Type' => 'application/json']);
    }
}
