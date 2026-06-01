<?php
/*
 * Created on   : Mon Jun 01 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SupplierObserver.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Observers;

use App\Models\{AuditLog, Supplier};
use Illuminate\Support\Facades\{Auth, Request};

class SupplierObserver {
    public function created(Supplier $supplier): void {
        $this->log($supplier, 'created', $supplier->getAttributes());
    }

    public function updated(Supplier $supplier): void {
        $changes = $supplier->getChanges();
        unset($changes['updated_at']);
        if ($changes === []) {
            return;
        }

        $event = 'updated';
        if (array_key_exists('archived_at', $changes)) {
            $event = $changes['archived_at'] === null ? 'restored' : 'archived';
        }

        $this->log($supplier, $event, [
            'before' => array_intersect_key($supplier->getOriginal(), $changes),
            'after' => $changes,
        ]);
    }

    public function deleted(Supplier $supplier): void {
        $this->log($supplier, 'deleted', $supplier->getOriginal());
    }

    /** @param  array<string, mixed>  $changes */
    private function log(Supplier $supplier, string $event, array $changes): void {
        AuditLog::query()->create([
            'organization_id' => $supplier->organization_id,
            'user_id' => Auth::id(),
            'event' => $event,
            'auditable_type' => Supplier::class,
            'auditable_id' => $supplier->id,
            'changes' => $changes,
            'ip' => Request::ip(),
            'user_agent' => substr((string) Request::userAgent(), 0, 255),
        ]);
    }
}
