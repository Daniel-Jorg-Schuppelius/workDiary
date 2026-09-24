<?php
/*
 * Created on   : Thu Sep 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MarkDeliveryInvoiced.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Listeners\Manufacturing;

use App\Enums\Manufacturing\DeliveryFacturationStatus;
use App\Events\Invoicing\DeliveryInvoiced;
use App\Listeners\ModuleListener;
use App\Services\Manufacturing\DeliveryService;

final class MarkDeliveryInvoiced extends ModuleListener {
    public function __construct(private readonly DeliveryService $deliveries) {}

    protected function module(): string {
        return 'manufacturing';
    }

    public function handle(DeliveryInvoiced $event): void {
        if (! $this->shouldHandle($event->delivery->organization_id)) {
            return;
        }
        $this->deliveries->markFacturationResult($event->delivery, DeliveryFacturationStatus::Invoiced);
    }
}
