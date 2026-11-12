<?php
/*
 * Created on   : Tue Nov 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CustomerDeletion.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Customer;

use App\Exceptions\{CustomerDeletionBlockedException, PartyDeletionBlockedException};
use App\Models\Customer\Customer;
use App\Models\Invoicing\IncomingEInvoice;
use App\Services\Ai\Contracts\AiMemory;
use App\Services\Retention\LegalHoldService;
use App\Services\Stammdaten\PartyDeletionGuard;
use Illuminate\Support\Facades\DB;

/**
 * Kundenlöschung für Oberfläche und API (MVP-1591): dieselben Sperren, das
 * KI-Gedächtnis des Kunden wird auditiert gelöscht, das Standardprojekt geht mit.
 */
final class CustomerDeletion {
    public function __construct(
        private readonly LegalHoldService $legalHolds,
        private readonly PartyDeletionGuard $guard,
        private readonly AiMemory $memory,
    ) {}

    /** @throws CustomerDeletionBlockedException */
    public function delete(Customer $customer): void {
        if ($this->legalHolds->activeHoldFor($customer) !== null) {
            throw CustomerDeletionBlockedException::legalHold();
        }
        // Am Sammelkunden hängen die Belege vieler Einmalkunden (MVP-1109).
        if ($customer->is_collective && IncomingEInvoice::query()->where('customer_id', $customer->id)->exists()) {
            throw CustomerDeletionBlockedException::collectiveWithDocuments();
        }
        if ($customer->hasNonDefaultProjects()) {
            throw CustomerDeletionBlockedException::projects();
        }
        if ($customer->externalReferences()->exists()) {
            throw CustomerDeletionBlockedException::externalReferences();
        }
        try {
            $this->guard->assertDeletable($customer);
        } catch (PartyDeletionBlockedException $e) {
            throw CustomerDeletionBlockedException::documents($e);
        }

        DB::transaction(function () use ($customer): void {
            // Einzel-Audit je Eintrag und Provider-Glossar-Hook statt stiller FK-Kaskade (Vollaudit 2026-07, M9).
            $this->memory->deleteForCustomer($customer->organization()->firstOrFail(), (int) $customer->id);
            $customer->projects()->where('is_default', true)->delete();
            $customer->delete();
        });
    }
}
