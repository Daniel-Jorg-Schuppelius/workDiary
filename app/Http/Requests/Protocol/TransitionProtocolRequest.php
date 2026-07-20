<?php
/*
 * Created on   : Tue Jul 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TransitionProtocolRequest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Requests\Protocol;

use App\Enums\Protocol\{ProtocolSignatureMethod, ProtocolSignatureRole};
use App\Http\Requests\BaseFormRequest;

/**
 * Validierung für Statusübergänge eines Protokolls. Die Regeln hängen von
 * der Aktion (Routenparameter `action`) ab: `returnToDraft` erlaubt einen
 * optionalen, `supersede` verlangt einen Grund; `sign` validiert den
 * Signaturblock nur, wenn `with_signature` gesetzt ist. Für alle übrigen
 * Aktionen gibt es keine Eingabefelder. Berechtigung trägt der Controller
 * (ProtocolPolicy je Aktion).
 */
class TransitionProtocolRequest extends BaseFormRequest {
    /** @return array<string, mixed> */
    public function rules(): array {
        return match ((string) $this->route('action')) {
            'returnToDraft' => ['reason' => ['nullable', 'string', 'max:2000']],
            'supersede' => ['reason' => ['required', 'string', 'max:2000']],
            'sign' => $this->boolean('with_signature') ? [
                // Rule::enum statt Handliste (Vollaudit 2026-07, N48).
                'signature.role' => ['required', 'string', \Illuminate\Validation\Rule::enum(ProtocolSignatureRole::class)],
                'signature.signer_name' => ['required', 'string', 'max:120'],
                'signature.signer_email' => ['nullable', 'email', 'max:180'],
                'signature.method' => ['required', 'string', \Illuminate\Validation\Rule::enum(ProtocolSignatureMethod::class)],
                'signature.signature_image_path' => ['nullable', 'string', 'max:255'],
            ] : [],
            default => [],
        };
    }

    /**
     * Signaturdaten für die `sign`-Aktion inkl. Nachweis-Metadaten
     * (IP/User-Agent) — null, wenn ohne Signatur signiert wird.
     *
     * @return array<string, mixed>|null
     */
    public function signaturePayload(): ?array {
        if (! $this->boolean('with_signature')) {
            return null;
        }

        /** @var array<string, mixed> $sig */
        $sig = $this->validated()['signature'];
        $sig['ip'] = $this->ip();
        $sig['user_agent'] = (string) $this->userAgent();

        return $sig;
    }
}
