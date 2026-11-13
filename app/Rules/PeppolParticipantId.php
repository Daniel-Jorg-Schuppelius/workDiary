<?php
/*
 * Created on   : Wed Aug 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PeppolParticipantId.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Rules;

use App\Services\Invoicing\EInvoice\LeitwegIdCheck;
use App\Services\Peppol\PeppolParticipantService;
use Closure;
use ERechnungToolkit\Entities\LeitwegId;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Peppol-Teilnehmerkennung (Feature 066, MVP-734): Form `<ICD>:<Kennung>`,
 * geprüft über {@see \ERechnungToolkit\Peppol\ParticipantId::tryParse()} —
 * ICD vierstellig numerisch, Wert nicht leer und höchstens 50 Zeichen. Ein
 * unbrauchbarer Wert soll beim Speichern auffallen, nicht erst beim Versand.
 * Unter Schema 0204 steht eine Leitweg-ID; mit falscher Prüfziffer ist die
 * Adresse nicht zustellbar, deshalb ein Fehler (MVP-1687).
 */
final class PeppolParticipantId implements ValidationRule {
    public function validate(string $attribute, mixed $value, Closure $fail): void {
        $participant = is_string($value) ? PeppolParticipantService::parse($value) : null;
        if ($participant === null) {
            $fail((string) __('validation.regex'));

            return;
        }
        $problem = $participant->getIcd() === LeitwegId::ICD ? LeitwegIdCheck::problem($participant->getIdentifier()) : null;
        if ($problem !== null) {
            $fail((string) __('peppol.field.participant_id_leitweg_invalid', ['reason' => $problem]));
        }
    }
}
