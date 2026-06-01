<?php
/*
 * Created on   : Mon Jun 01 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ForeignCustomerObserver.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Observers;

use App\Models\{AuditLog, ForeignCustomer};
use Illuminate\Support\Facades\{Auth, Request};

class ForeignCustomerObserver {
    public function created(ForeignCustomer $foreignCustomer): void {
        $this->log($foreignCustomer, 'created', $foreignCustomer->getAttributes());
    }

    public function updated(ForeignCustomer $foreignCustomer): void {
        $changes = $foreignCustomer->getChanges();
        unset($changes['updated_at']);
        if ($changes === []) {
            return;
        }

        $event = 'updated';
        if (array_key_exists('archived_at', $changes)) {
            $event = $changes['archived_at'] === null ? 'restored' : 'archived';
        }

        $this->log($foreignCustomer, $event, [
            'before' => array_intersect_key($foreignCustomer->getOriginal(), $changes),
            'after' => $changes,
        ]);
    }

    public function deleted(ForeignCustomer $foreignCustomer): void {
        $this->log($foreignCustomer, 'deleted', $foreignCustomer->getOriginal());
    }

    /** @param  array<string, mixed>  $changes */
    private function log(ForeignCustomer $foreignCustomer, string $event, array $changes): void {
        AuditLog::query()->create([
            'organization_id' => $foreignCustomer->organization_id,
            'user_id' => Auth::id(),
            'event' => $event,
            'auditable_type' => ForeignCustomer::class,
            'auditable_id' => $foreignCustomer->id,
            'changes' => $changes,
            'ip' => Request::ip(),
            'user_agent' => substr((string) Request::userAgent(), 0, 255),
        ]);
    }
}
