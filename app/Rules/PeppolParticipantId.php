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

use App\Services\Peppol\PeppolParticipantService;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Peppol-Teilnehmerkennung (Feature 066, MVP-734): Form `<ICD>:<Kennung>`,
 * geprüft über {@see \ERechnungToolkit\Peppol\ParticipantId::tryParse()} —
 * ICD vierstellig numerisch, Wert nicht leer und höchstens 50 Zeichen. Ein
 * unbrauchbarer Wert soll beim Speichern auffallen, nicht erst beim Versand.
 */
final class PeppolParticipantId implements ValidationRule {
    public function validate(string $attribute, mixed $value, Closure $fail): void {
        if (! is_string($value) || PeppolParticipantService::parse($value) === null) {
            $fail((string) __('validation.regex'));
        }
    }
}
