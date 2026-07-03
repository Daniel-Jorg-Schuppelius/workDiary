<?php
/*
 * Created on   : Mon May 18 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LicenseController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers;

use App\Models\{AuditLog, User};
use App\Services\Licensing\LicenseService;
use CommonToolkit\Helper\Data\CryptoHelper;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class LicenseController extends Controller {
    public function __construct(private readonly LicenseService $service) {}

    public function show(Request $request): View {
        $result = $this->service->current($request->getHost());

        return view('licensing.required', [
            'status' => $result->status,
            'message' => $result->message,
            'host' => $request->getHost(),
        ]);
    }

    public function store(Request $request): RedirectResponse {
        $data = $request->validate([
            'license_key' => ['required', 'string', 'max:8192'],
        ]);

        $result = $this->service->install($data['license_key']);

        if (! $result->isUsable()) {
            return back()->withErrors([
                'license_key' => $result->message ?? 'Lizenz konnte nicht installiert werden (' . $result->status->value . ').',
            ]);
        }

        $this->writeInstalledAudit($request->user(), $result);

        return redirect('/')->with('status', 'Lizenz erfolgreich aktiviert.');
    }

    private function writeInstalledAudit(?User $user, \App\Services\Licensing\LicenseResult $result): void {
        $payload = $result->payload;
        $licenseHash = $payload !== null ? CryptoHelper::hash($payload->licenseId) : null;

        // Der Audit-Eintrag ist Protokollierung – ein fehlgeschlagener
        // Schreibvorgang (z. B. nicht erreichbare DB) darf die erfolgreiche
        // Lizenzaktivierung nicht mit einem 500 zunichtemachen.
        try {
            AuditLog::query()->create([
                'organization_id' => $user?->organization_id,
                'user_id' => $user?->id,
                'event' => 'license.installed',
                'auditable_type' => User::class,
                'auditable_id' => $user->id ?? 0,
                'changes' => [
                    'license_id_sha256' => $licenseHash,
                    'status' => $result->status->value,
                    'licensee' => $payload?->licensee,
                    'expires_at' => $payload?->expiresAt?->toIso8601String(),
                ],
            ]);
        } catch (\Throwable $e) {
            Log::warning('Audit-Eintrag zur Lizenzinstallation fehlgeschlagen.', ['exception' => $e->getMessage()]);
        }
    }
}
