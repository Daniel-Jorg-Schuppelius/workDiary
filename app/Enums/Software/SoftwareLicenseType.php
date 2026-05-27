<?php
/*
 * Created on   : Wed May 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SoftwareLicenseType.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Enums\Software;

enum SoftwareLicenseType: string {
    case Perpetual = 'perpetual';
    case Subscription = 'subscription';
    case Oem = 'oem';
    case Volume = 'volume';
    case Free = 'free';
    case OpenSource = 'open_source';
    case Other = 'other';

    public function label(): string {
        return match ($this) {
            self::Perpetual    => __('Kauflizenz'),
            self::Subscription => __('Abonnement'),
            self::Oem          => __('OEM'),
            self::Volume       => __('Volumenlizenz'),
            self::Free         => __('Kostenfrei'),
            self::OpenSource   => __('Open Source'),
            self::Other        => __('Sonstige'),
        };
    }
}
