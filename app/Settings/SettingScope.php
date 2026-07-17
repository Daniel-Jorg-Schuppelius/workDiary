<?php
/*
 * Created on   : Wed Jul 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SettingScope.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Settings;

/**
 * Erlaubte Ablage-Ebenen einer Einstellung (Feature 067, MVP-173).
 *
 * System       = system_settings (Betreiber, UI-schreibbar)
 * Organization = organizations.settings (Org-Override, auditiert)
 * User         = users.preferences (nur echte Nutzerpräferenzen)
 */
enum SettingScope: string {
    case System = 'system';
    case Organization = 'organization';
    case User = 'user';

    public function label(): string {
        return __('settingsregistry.scopes.' . $this->value);
    }
}
