<?php
/*
 * Created on   : Sun Jun 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PermitStatus.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Enums\Permit;

enum PermitStatus: string {
    case Required = 'required';
    case Applied = 'applied';
    case Granted = 'granted';
    case Rejected = 'rejected';
    case Expired = 'expired';

    public function label(): string {
        return match ($this) {
            self::Required => __('permit.status.required'),
            self::Applied  => __('permit.status.applied'),
            self::Granted  => __('permit.status.granted'),
            self::Rejected => __('permit.status.rejected'),
            self::Expired  => __('permit.status.expired'),
        };
    }

    /** Badge-Tonalität für die UI. */
    public function tone(): string {
        return match ($this) {
            self::Required => 'warning',
            self::Applied  => 'info',
            self::Granted  => 'success',
            self::Rejected => 'error',
            self::Expired  => 'ghost',
        };
    }
}
