<?php
/*
 * Created on   : Thu Jul 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AiMemoryEntryType.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Ai;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/**
 * Eintragstypen des KI-Gedächtnisses (Feature 025, MVP-401): Glossar
 * (Begriff → Bedeutung, optional Zielübersetzungen), Stil-/Schreibregel
 * (freie Anweisung) und Beispielpaar (Rohtext → Zieltext, Few-Shot).
 */
enum AiMemoryEntryType: string implements HasLabel {
    use HasOptions;

    case Glossary = 'glossary';
    case StyleRule = 'style_rule';
    case Example = 'example';

    public function label(): string {
        return (string) __('enums.ai.memory_type.' . $this->value);
    }
}
