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
use App\Models\Protocol\Protocol;
use App\Services\Attachments\FileAttacher;
use Illuminate\Validation\Rule;

/**
 * Validierung für Statusübergänge eines Protokolls. Die Regeln hängen von
 * der Aktion (Routenparameter `action`) ab: `returnToDraft` erlaubt einen
 * optionalen, `supersede` verlangt einen Grund; `sign` validiert den
 * Signaturblock, wenn `with_signature` gesetzt ist oder der Protokolltyp noch
 * Pflichtunterschriften verlangt (MVP-1572). Für alle übrigen Aktionen gibt
 * es keine Eingabefelder. Berechtigung trägt der Controller (ProtocolPolicy
 * je Aktion).
 */
class TransitionProtocolRequest extends BaseFormRequest {
    /** @return array<string, mixed> */
    public function rules(): array {
        return match ((string) $this->route('action')) {
            'returnToDraft' => ['reason' => ['nullable', 'string', 'max:2000']],
            'supersede' => ['reason' => ['required', 'string', 'max:2000']],
            'sign' => $this->withSignature() ? [
                'signature.role' => ['required', 'string', Rule::enum(ProtocolSignatureRole::class)],
                'signature.signer_name' => ['required', 'string', 'min:2', 'max:120'],
                'signature.signer_email' => ['nullable', 'email', 'max:180'],
                // Im Haus gibt es nur „vor Ort“ und „Papier“; Portal und Link belegen sich über ihren eigenen Weg.
                'signature.method' => ['required', 'string', Rule::enum(ProtocolSignatureMethod::class)->only([ProtocolSignatureMethod::Onscreen, ProtocolSignatureMethod::Paper])],
                'signature.image' => ['nullable', 'required_if:signature.method,' . ProtocolSignatureMethod::Onscreen->value, 'string', 'starts_with:data:image/png;base64,', 'max:1500000'],
                'signature.scan' => ['nullable', 'required_if:signature.method,' . ProtocolSignatureMethod::Paper->value, 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:' . FileAttacher::maxKb()],
                'signature.original_confirmed' => ['accepted_if:signature.method,' . ProtocolSignatureMethod::Paper->value],
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
        if (! $this->withSignature()) {
            return null;
        }

        /** @var array<string, mixed> $sig */
        $sig = $this->validated()['signature'];
        $paper = ($sig['method'] ?? null) === ProtocolSignatureMethod::Paper->value;

        return [
            'role' => $sig['role'],
            'signer_name' => $sig['signer_name'],
            'signer_email' => $sig['signer_email'] ?? null,
            'method' => $sig['method'],
            'signature_image' => $paper ? null : ($sig['image'] ?? null),
            'signature_scan' => $paper ? $this->file('signature.scan') : null,
            'original_confirmed' => $paper,
            'ip' => $this->ip(),
            'user_agent' => (string) $this->userAgent(),
        ];
    }

    /** Fehlt eine Pflichtunterschrift, ist der Signaturblock nicht abwählbar. */
    private function withSignature(): bool {
        $protocol = $this->route('protocol');

        return $this->boolean('with_signature')
            || ($protocol instanceof Protocol && $protocol->missingSignatureRoles() !== []);
    }
}
