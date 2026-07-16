<?php
/*
 * Created on   : Thu Jul 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AiSensitivity.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Ai;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/**
 * Sensibilitätsstufe einer KI-Capability (Feature 025, MVP-398). Die
 * Stufe entscheidet zusammen mit dem Branchenprofil der Organisation,
 * ob Cloud-Verbindungen zulässig sind (Provider-Matrix in `config/ai.php`,
 * ausgewertet im {@see \App\Services\Ai\AiRoutingResolver}). Routing
 * wechselt nie stillschweigend über eine Sensibilitätsgrenze.
 */
enum AiSensitivity: string implements HasLabel {
    use HasOptions;

    case Low = 'low';
    case Medium = 'medium';
    case High = 'high';

    public function label(): string {
        return (string) __('enums.ai.sensitivity.' . $this->value);
    }
}
