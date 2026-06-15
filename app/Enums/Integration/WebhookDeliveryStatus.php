<?php
/*
 * Created on   : Sat Jun 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : WebhookDeliveryStatus.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Integration;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/**
 * Zustellstatus eines einzelnen Webhook-Auslieferungsversuchs (Feature 008).
 */
enum WebhookDeliveryStatus: string implements HasLabel {
    use HasOptions;

    case Pending = 'pending';
    case Success = 'success';
    case Failed = 'failed';

    public function label(): string {
        return (string) __('integration.webhook.delivery_status.' . $this->value);
    }

    /** Tone-Token für x-status-badge. */
    public function tone(): string {
        return match ($this) {
            self::Pending => 'ghost',
            self::Success => 'success',
            self::Failed => 'error',
        };
    }
}
