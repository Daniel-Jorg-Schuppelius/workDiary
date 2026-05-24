<?php
/*
 * Created on   : Fri May 15 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CustomerObserver.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Observers;

use App\Models\{AuditLog, Customer};
use Illuminate\Support\Facades\{Auth, Request};

class CustomerObserver {
    public function created(Customer $customer): void {
        $this->log($customer, 'created', $customer->getAttributes());
        // Standardprojekt automatisch anlegen, damit Ad-hoc-/Notfallaufträge
        // sofort einen sauberen Container für Stundenzettel/Zeiteinträge haben.
        $customer->defaultProjectOrCreate();
    }

    public function updated(Customer $customer): void {
        $changes = $customer->getChanges();
        unset($changes['updated_at']);
        if ($changes === []) {
            return;
        }

        $event = 'updated';
        if (array_key_exists('archived_at', $changes)) {
            $event = $changes['archived_at'] === null ? 'restored' : 'archived';
        }

        $this->log($customer, $event, [
            'before' => array_intersect_key($customer->getOriginal(), $changes),
            'after' => $changes,
        ]);
    }

    public function deleted(Customer $customer): void {
        $this->log($customer, 'deleted', $customer->getOriginal());
    }

    /** @param  array<string, mixed>  $changes */
    private function log(Customer $customer, string $event, array $changes): void {
        AuditLog::query()->create([
            'organization_id' => $customer->organization_id,
            'user_id' => Auth::id(),
            'event' => $event,
            'auditable_type' => Customer::class,
            'auditable_id' => $customer->id,
            'changes' => $changes,
            'ip' => Request::ip(),
            'user_agent' => substr((string) Request::userAgent(), 0, 255),
        ]);
    }
}
