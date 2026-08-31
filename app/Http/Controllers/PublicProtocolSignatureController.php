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

use App\Services\Customer\CustomerQueryService;
use App\Services\Protocol\ProtocolSignatureTokenService;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\View\View;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

class PublicProtocolSignatureController extends Controller {
    public function __construct(
        private readonly ProtocolSignatureTokenService $tokens,
        private readonly CustomerQueryService $queries,
    ) {}

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
            'queries' => $this->queriesForToken($record),
        ]);
    }

    public function store(Request $request, string $token): RedirectResponse|Response {
        $data = $request->validate([
            'signer_name' => ['required', 'string', 'min:2', 'max:120'],
            // 'signature_image_path' NICHT vom Client annehmen (Sicherheitsscan
            // 2026-08-23, S-63): der Endpunkt ist nur tokengeschützt, und ein
            // beliebiger Pfad landete unverändert in der Datenbank. Heute wird
            // er nirgends gerendert — ein späterer PDF- oder View-Renderer, der
            // ihn als Bildquelle lädt, verarbeitete damit Pfadangaben aus einer
            // unauthentifizierten Quelle (`storage/…`, `phar://`). Gesetzt wird
            // er serverseitig, sobald ein Bild gespeichert wird.
            'accept' => ['accepted'],
        ]);

        try {
            $this->tokens->redeem($token, [
                'signer_name' => $data['signer_name'],
                'signature_image_path' => null,
                'ip' => $request->ip(),
                'user_agent' => substr((string) $request->userAgent(), 0, 255),
            ]);
        } catch (RuntimeException $e) {
            return response()->view('public.protocol-sign-error', ['message' => $e->getMessage()], 410);
        }

        return redirect()->route('protocols.public-sign', ['token' => $token])
            ->with('success', __('protocol.signature.redeemed'));
    }

    /**
     * Ablehnung mit Pflicht-Begründung und (optional) einzelnen Mängeln, die
     * als Offene Punkte am Auftrag/Protokoll erfasst werden.
     */
    public function reject(Request $request, string $token): RedirectResponse|Response {
        $data = $request->validate([
            'signer_name' => ['nullable', 'string', 'max:120'],
            'reason' => ['required', 'string', 'min:3', 'max:2000'],
            'issues' => ['nullable', 'array', 'max:50'],
            'issues.*' => ['nullable', 'string', 'max:200'],
        ]);

        try {
            $this->tokens->reject($token, [
                'signer_name' => $data['signer_name'] ?? null,
                'reason' => $data['reason'],
                'issues' => $data['issues'] ?? [],
                'ip' => $request->ip(),
                'user_agent' => substr((string) $request->userAgent(), 0, 255),
            ]);
        } catch (RuntimeException $e) {
            return response()->view('public.protocol-sign-error', ['message' => $e->getMessage()], 410);
        }

        return redirect()->route('protocols.public-sign', ['token' => $token])
            ->with('success', __('protocol.signature.rejected'));
    }

    /**
     * Rückfrage des Kunden zum vorgelegten Vorgang (Freitext). Wird intern als
     * CustomerQuery erfasst und benachrichtigt die zuständige Rolle.
     */
    public function query(Request $request, string $token): RedirectResponse|Response {
        $data = $request->validate([
            'asker_name' => ['nullable', 'string', 'max:120'],
            'question' => ['required', 'string', 'min:3', 'max:2000'],
        ]);

        $record = $this->tokens->find($token);
        if ($record === null || ! $record->expires_at->isFuture()) {
            return response()->view('public.protocol-sign-error', [
                'message' => __('protocol.signature.tokenExpired'),
            ], 410);
        }

        $protocol = $record->protocol()->firstOrFail();
        $subject = $protocol->subject instanceof \Illuminate\Database\Eloquent\Model ? $protocol->subject : $protocol;
        $customerId = $subject->getAttribute('customer_id');

        $this->queries->raise($subject, [
            'organization_id' => (int) $protocol->organization_id,
            'customer_id' => $customerId !== null ? (int) $customerId : null,
            'signature_token_id' => $record->id,
            'asker_name' => $data['asker_name'] ?? $record->signer_name,
            'asker_email' => $record->signer_email,
            'question' => $data['question'],
        ]);

        return redirect()->route('protocols.public-sign', ['token' => $token])
            ->with('success', __('protocol.signature.queryRaised'));
    }

    /**
     * Bisherige Rückfragen zu diesem Vorgang — strikt auf den Vorgang des
     * Tokens beschränkt (keine fremden/internen Inhalte). Frage + Antwort.
     *
     * @return \Illuminate\Support\Collection<int, \App\Models\CustomerQuery>
     */
    private function queriesForToken(\App\Models\ProtocolSignatureToken $record): \Illuminate\Support\Collection {
        return \App\Models\CustomerQuery::query()
            ->withoutGlobalScopes()
            ->where('organization_id', $record->protocol?->organization_id)
            ->where('signature_token_id', $record->id)
            ->orderBy('created_at')
            ->get();
    }
}
