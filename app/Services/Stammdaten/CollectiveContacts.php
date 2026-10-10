<?php
/*
 * Created on   : Sat Oct 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CollectiveContacts.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Stammdaten;

use App\Enums\Billing\DocumentDirection;
use App\Exceptions\CollectiveContactException;
use App\Models\Customer\Customer;
use App\Models\Invoicing\IncomingEInvoice;
use App\Models\Platform\Organization;
use App\Models\Supplier\Supplier;
use Illuminate\Support\Facades\DB;

/**
 * Sammellieferant und Sammelkunde (Feature 163, MVP-1109): je Organisation
 * höchstens einer je Art, angelegt bei Bedarf. Der Name der echten Partei
 * bleibt am Beleg; der Sammelkontakt hat keine Anschrift und keine
 * Bankverbindung und wird nie an ein Buchhaltungssystem übertragen —
 * dort steht er für dessen eigenen Sammelkontakt.
 */
class CollectiveContacts {
    /** Steuerkategorien (BT-151), bei denen Lexware einen echten Firmenkontakt verlangt. */
    private const NAMED_CONTACT_CATEGORIES = ['AE', 'K', 'G'];

    public function supplier(Organization $organization): Supplier {
        return DB::transaction(static function () use ($organization): Supplier {
            // Zeilensperre der Organisation: zwei gleichzeitige Erstanlagen ergäben zwei Sammelkontakte.
            Organization::query()->whereKey($organization->id)->lockForUpdate()->first();
            $existing = Supplier::query()->withoutGlobalScopes()->where('organization_id', $organization->id)->where('is_collective', true)->first();
            if ($existing !== null) {
                return $existing;
            }
            $supplier = new Supplier(['organization_id' => $organization->id, 'name' => (string) __('Sammellieferant'), 'active' => true]);
            $supplier->forceFill(['is_collective' => true])->save();

            return $supplier;
        });
    }

    public function customer(Organization $organization): Customer {
        return DB::transaction(static function () use ($organization): Customer {
            Organization::query()->whereKey($organization->id)->lockForUpdate()->first();
            $existing = Customer::query()->withoutGlobalScopes()->where('organization_id', $organization->id)->where('is_collective', true)->first();
            if ($existing !== null) {
                return $existing;
            }
            $customer = new Customer(['organization_id' => $organization->id, 'name' => (string) __('Sammelkunde')]);
            $customer->forceFill(['is_collective' => true])->save();

            return $customer;
        });
    }

    /** Vor jedem Stammdaten-Push in ein Buchhaltungssystem. */
    public static function assertPushable(Supplier|Customer $party): void {
        if ($party->is_collective) {
            throw new CollectiveContactException((string) __('Ein Sammelkontakt wird nicht an ein Buchhaltungssystem übertragen.'));
        }
    }

    /**
     * Reverse Charge (§ 13b), innergemeinschaftliche Lieferung bzw. Erwerb und
     * Drittland: Lexware erlaubt dafür keinen Sammelkontakt, der Kontakt muss
     * eine Firma mit USt-IdNr. bzw. Auslandsanschrift sein.
     */
    public static function requiresNamedContact(IncomingEInvoice $incoming): bool {
        $summary = (array) $incoming->summary;
        foreach ((array) ($summary['tax_breakdown'] ?? []) as $subtotal) {
            if (in_array(is_array($subtotal) ? ($subtotal['category'] ?? null) : null, self::NAMED_CONTACT_CATEGORIES, true)) {
                return true;
            }
        }
        $country = $incoming->direction === DocumentDirection::Outgoing ? ($summary['buyer_country'] ?? null) : ($summary['seller_country'] ?? null);

        return is_string($country) && $country !== '' && strtoupper($country) !== 'DE';
    }
}
