<?php
/*
 * Created on   : Tue Jun 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ProviderCapability.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Inventory;

/**
 * Fähigkeiten eines Bestandsproviders (Feature 048, MVP-066). Ein Provider
 * deklariert seine Capabilities, damit die Oberfläche/Service-Schicht nur
 * unterstützte Aktionen anbietet; nicht unterstützte werden blockiert.
 */
enum ProviderCapability: string {
    case ReadStock = 'read_stock';
    case CheckAvailability = 'check_availability';
    case Reserve = 'reserve';
    case ReleaseReservation = 'release_reservation';
    case PostConsumption = 'post_consumption';
    case PostReceipt = 'post_receipt';
    case PostReturn = 'post_return';
    case PostTransfer = 'post_transfer';
    case PostCorrection = 'post_correction';
    case ReceiveFinishedGood = 'receive_finished_good';
}
