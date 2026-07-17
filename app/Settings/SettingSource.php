<?php
/*
 * Created on   : Wed Jul 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SettingSource.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Settings;

/**
 * Herkunft eines effektiven Einstellungswerts (Feature 067, MVP-173/174).
 * Grundlage der "Effektiver Wert + Herkunft"-Anzeige.
 */
enum SettingSource: string {
    case Organization = 'organization';
    case System = 'system';
    case Config = 'config';
    case Default = 'default';

    public function label(): string {
        return __('settingsregistry.sources.' . $this->value);
    }
}
