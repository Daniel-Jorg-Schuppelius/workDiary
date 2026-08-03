<?php
/*
 * Created on   : Thu Jul 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AiProviderNotImplementedException.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Ai\Exceptions;

use App\Enums\Ai\AiProviderType;

/**
 * Für den Provider-Typ existiert noch kein Adapter — die Adapter folgen
 * in MVP-407 bis MVP-410. Verbindungen solcher Typen können angelegt,
 * aber nicht aufgerufen werden.
 */
class AiProviderNotImplementedException extends AiException {
    public static function forType(AiProviderType $type): self {
        return new self((string) __('ai.error.provider_not_implemented', ['provider' => $type->value]));
    }
}
