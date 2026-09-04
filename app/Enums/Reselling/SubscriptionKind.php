<?php
/*
 * Created on   : Fri Sep 04 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SubscriptionKind.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Reselling;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/**
 * Art einer weiterverkauften Leistung (Feature 152). Generisch, damit Lizenzen,
 * Domains und Hosting in einem Register liegen.
 */
enum SubscriptionKind: string implements HasLabel {
    use HasOptions;

    case License = 'license';
    case Domain = 'domain';
    case Hosting = 'hosting';
    case Mailbox = 'mailbox';
    case Backup = 'backup';
    case Other = 'other';

    public function label(): string {
        return (string) __('resale.kind.' . $this->value);
    }

    public function icon(): string {
        return match ($this) {
            self::License => 'verified',
            self::Domain => 'language',
            self::Hosting => 'dns',
            self::Mailbox => 'mail',
            self::Backup => 'backup',
            self::Other => 'subscriptions',
        };
    }
}
