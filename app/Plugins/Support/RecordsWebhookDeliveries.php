<?php
/*
 * Created on   : Wed Jul 29 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RecordsWebhookDeliveries.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Support;

use CommonToolkit\Helper\Data\CryptoHelper;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\UniqueConstraintViolationException;

/**
 * Persist-before-process-Deduplizierung der Webhook-Controller (W3d):
 * die Delivery-Zeile wird VOR der Verarbeitung angelegt, Replays laufen in
 * den Unique-Index und liefern null. Der einheitliche duplicate/queued-
 * Response bleibt beim Controller, die getrennten Tabellen je Provider auch.
 */
trait RecordsWebhookDeliveries {
    /**
     * Legt die Delivery-Zeile an; null bei bereits verbuchter Zustellung
     * (Unique-Constraint → Duplikat).
     *
     * @template TModel of Model
     *
     * @param  callable(): TModel  $create  legt die Delivery-Zeile per create() an
     * @return TModel|null
     */
    protected function recordDelivery(callable $create): ?Model {
        try {
            return $create();
        } catch (UniqueConstraintViolationException) {
            return null;
        }
    }

    /** Inhaltsbasierte Dedup-Kennung über den unveränderten Raw-Body. */
    protected function deliveryHash(string $raw): string {
        return CryptoHelper::hash($raw);
    }
}
