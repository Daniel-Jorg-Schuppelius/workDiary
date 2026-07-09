<?php
/*
 * Created on   : Wed Jul 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : EffectiveValue.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Settings;

/**
 * Effektiver Wert einer Einstellung samt erklärbarer Herkunft
 * (Feature 067, MVP-173; Anzeige in MVP-174/179).
 */
final readonly class EffectiveValue {
    public function __construct(
        public mixed $value,
        public SettingSource $source,
        public SettingDefinition $definition,
    ) {}

    /** Redaktierte Darstellung für Export/Supportbericht (MVP-179). */
    public function exportValue(): mixed {
        return $this->definition->sensitive ? '<redacted>' : $this->value;
    }
}
