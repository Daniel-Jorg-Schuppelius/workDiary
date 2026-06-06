<?php
/*
 * Created on   : Sun May 31 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PublicProtocolSignatureController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers;

use App\Services\Protocol\ProtocolSignatureTokenService;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\View\View;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

class PublicProtocolSignatureController extends Controller {
    public function __construct(private readonly ProtocolSignatureTokenService $tokens) {}

    public function show(string $token): View|Response {
        try {
            $record = $this->tokens->open($token);
        } catch (RuntimeException $e) {
            return response()->view('public.protocol-sign-error', ['message' => $e->getMessage()], 410);
        }

        $protocol = $record->protocol()->with(['items', 'subject'])->firstOrFail();

        // Org-Kontext aus dem Protokoll binden, damit Anzeige-Zeitzone (Tz)
        // korrekt aufgelöst wird statt auf den globalen Fallback zu fallen.
        if (! empty($protocol->organization_id)) {
            $org = \App\Models\Organization::query()->withoutGlobalScopes()->find($protocol->organization_id);
            if ($org instanceof \App\Models\Organization) {
                app()->instance('currentOrganization', $org);
            }
        }

        return view('public.protocol-sign', [
            'token' => $token,
            'record' => $record,
            'protocol' => $protocol,
        ]);
    }

    public function store(Request $request, string $token): RedirectResponse|Response {
        $data = $request->validate([
            'signer_name' => ['required', 'string', 'min:2', 'max:120'],
            'signature_image_path' => ['nullable', 'string', 'max:255'],
            'accept' => ['accepted'],
        ]);

        try {
            $this->tokens->redeem($token, [
                'signer_name' => $data['signer_name'],
                'signature_image_path' => $data['signature_image_path'] ?? null,
                'ip' => $request->ip(),
                'user_agent' => substr((string) $request->userAgent(), 0, 255),
            ]);
        } catch (RuntimeException $e) {
            return response()->view('public.protocol-sign-error', ['message' => $e->getMessage()], 410);
        }

        return redirect()->route('protocols.public-sign', ['token' => $token])
            ->with('success', __('protocol.signature.redeemed'));
    }
}
