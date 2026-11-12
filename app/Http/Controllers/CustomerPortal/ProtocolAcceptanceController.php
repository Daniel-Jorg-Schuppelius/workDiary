<?php
/*
 * Created on   : Tue Nov 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ProtocolAcceptanceController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\CustomerPortal;

use App\Enums\Protocol\{ProtocolSignatureMethod, ProtocolSignatureRole};
use App\Exceptions\{InvalidProtocolTransitionException, ProtocolValidationException};
use App\Http\Controllers\Concerns\ResolvesPortalUser;
use App\Http\Controllers\Controller;
use App\Models\Asset\Asset;
use App\Models\Diary\DiaryEntry;
use App\Models\Protocol\Protocol;
use App\Services\Protocol\ProtocolService;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\View\View;

/**
 * Abnahme im Kundenportal (MVP-1572): Ein zur Prüfung eingereichtes,
 * kundensichtbares Protokoll des eigenen Auftrags oder Objekts ansehen und
 * unterschreiben. Dieselben Angaben wie am Signaturlink (Name, gezeichnete
 * Unterschrift, Bestätigung) oder mit Begründung ablehnen; abgeschlossen wird
 * das Protokoll im Haus.
 */
class ProtocolAcceptanceController extends Controller {
    use ResolvesPortalUser;

    public function __construct(private readonly ProtocolService $protocols) {}

    public function showDiary(DiaryEntry $diary, Protocol $protocol): View {
        return $this->show($diary, $protocol, 'diary');
    }

    public function showAsset(Asset $asset, Protocol $protocol): View {
        return $this->show($asset, $protocol, 'assets');
    }

    public function signDiary(Request $request, DiaryEntry $diary, Protocol $protocol): RedirectResponse {
        return $this->sign($request, $diary, $protocol, 'diary');
    }

    public function signAsset(Request $request, Asset $asset, Protocol $protocol): RedirectResponse {
        return $this->sign($request, $asset, $protocol, 'assets');
    }

    public function rejectDiary(Request $request, DiaryEntry $diary, Protocol $protocol): RedirectResponse {
        return $this->reject($request, $diary, $protocol, 'diary');
    }

    public function rejectAsset(Request $request, Asset $asset, Protocol $protocol): RedirectResponse {
        return $this->reject($request, $asset, $protocol, 'assets');
    }

    private function show(Asset|DiaryEntry $subject, Protocol $protocol, string $area): View {
        $this->assertAwaiting($subject, $protocol);

        return view('customer.protocols.accept', [
            'protocol' => $protocol->load('items'),
            'subject' => $subject,
            'user' => $this->portalUser(),
            'action' => route("customer.{$area}.protocols.accept.sign", [$subject, $protocol]),
            'rejectAction' => route("customer.{$area}.protocols.accept.reject", [$subject, $protocol]),
            'backUrl' => route("customer.{$area}.show", $subject),
        ]);
    }

    private function sign(Request $request, Asset|DiaryEntry $subject, Protocol $protocol, string $area): RedirectResponse {
        $this->assertAwaiting($subject, $protocol);
        $user = $this->portalUser();

        $data = $request->validate([
            'signer_name' => ['required', 'string', 'min:2', 'max:120'],
            'signature' => ['required', 'string', 'starts_with:data:image/png;base64,', 'max:1500000'],
            'accept' => ['accepted'],
        ]);

        try {
            $signature = $this->protocols->captureSignature($protocol, $user, [
                'role' => ProtocolSignatureRole::Customer->value,
                'method' => ProtocolSignatureMethod::Portal->value,
                'signer_name' => $data['signer_name'],
                'signer_email' => $user->email,
                'signature_image' => $data['signature'],
                'ip' => $request->ip(),
                'user_agent' => substr((string) $request->userAgent(), 0, 255),
            ]);
        } catch (ProtocolValidationException $e) {
            return back()->withErrors(['signature' => implode(' • ', $e->errors())])->withInput($request->except('signature'));
        } catch (InvalidProtocolTransitionException $e) {
            return back()->withErrors(['signature' => $e->getMessage()]);
        }

        $this->protocols->notifyCustomerSigned($protocol, $this->protocols->reviewRequester($protocol), $signature->signer_name);

        return redirect()->route("customer.{$area}.show", $subject)->with('success', __('protocol.signature.redeemed'));
    }

    /** Ablehnung mit Begründung wie am Signaturlink: offene Punkte am Auftrag, Protokoll zurück an das Haus. */
    private function reject(Request $request, Asset|DiaryEntry $subject, Protocol $protocol, string $area): RedirectResponse {
        $this->assertAwaiting($subject, $protocol);
        $user = $this->portalUser();

        $data = $request->validate([
            'reason' => ['required', 'string', 'min:3', 'max:2000'],
            'issues' => ['nullable', 'array', 'max:50'],
            'issues.*' => ['nullable', 'string', 'max:200'],
        ]);

        $this->protocols->rejectByCustomer($protocol, $user, $this->protocols->reviewRequester($protocol), [
            'reason' => $data['reason'],
            'signer_name' => $user->name,
            'issues' => array_values(array_filter($data['issues'] ?? [], 'is_string')),
            'ip' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 255),
            'channel' => ProtocolSignatureMethod::Portal->value,
        ]);

        return redirect()->route("customer.{$area}.show", $subject)->with('success', __('protocol.signature.rejected'));
    }

    /** Eigener Auftrag bzw. eigenes Objekt, und das Protokoll wartet dort auf die Abnahme — sonst 404. */
    private function assertAwaiting(Asset|DiaryEntry $subject, Protocol $protocol): void {
        $user = $this->portalUser();

        // Ohne Organisationskontext greift kein Scope: Zugehörigkeit ausdrücklich prüfen.
        abort_unless(
            (int) $subject->customer_id === (int) $user->customer_id
            && (int) $subject->organization_id === (int) $user->organization_id
            && $subject->protocols()->awaitingCustomerSignature()->whereKey($protocol->getKey())->exists(),
            404,
        );
    }
}
