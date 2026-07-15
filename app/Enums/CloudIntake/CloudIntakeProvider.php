<?php
/*
 * Created on   : Tue Jul 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CloudIntakeProvider.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Enums\CloudIntake;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/**
 * Provider des Cloud-Dokumenteingangs (Feature 080). Der Wert ist zugleich
 * die Plugin-ID des zuständigen Adapters ({@see \App\Plugins\Contracts\DocumentIntakeSource}).
 */
enum CloudIntakeProvider: string implements HasLabel {
    use HasOptions;

    case Dropbox = 'dropbox';
    case Microsoft = 'microsoft';
    case Google = 'google';

    public function label(): string {
        return (string) __('enums.cloud_intake.provider.' . $this->value);
    }

    /** Plugin-ID des Adapters in der Plugin-Registry. */
    public function pluginId(): string {
        return match ($this) {
            self::Dropbox => 'dropbox',
            self::Microsoft => 'msgraph',
            self::Google => 'google-drive',
        };
    }
}
