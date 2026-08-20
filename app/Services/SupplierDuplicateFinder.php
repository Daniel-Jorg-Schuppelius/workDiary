<?php
/*
 * Created on   : Thu Aug 20 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SupplierDuplicateFinder.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services;

use App\Models\{Organization, Supplier, SupplierMergeDismissal};
use App\Services\Integration\Match\{EntityMatcher, MatchProfile};
use App\Services\Integration\Profiles\SupplierMatchProfile;
use Illuminate\Database\Eloquent\{Collection as EloquentCollection, Model};

/**
 * Findet Dubletten-Kandidaten unter den Lieferanten einer Organisation (Audit
 * 2026-08, W2.3). Lieferanten entstehen über vier unabhängige Pfade — manuell,
 * Integrations-Inbox-Auto-Create, Lexoffice-Kontakt-Sync und CSV-Import —,
 * das Dubletten-Risiko ist damit dasselbe wie beim Kunden nach dem
 * Toggl-Import.
 *
 * Nutzt das vorhandene {@see SupplierMatchProfile} (USt-IdNr./Lieferantennummer
 * exakt, E-Mail und Firma+PLZ wahrscheinlich) + {@see EntityMatcher};
 * Paar-Schleife/Dismissal-Filter siehe {@see AbstractDuplicateFinder}.
 *
 * @extends AbstractDuplicateFinder<Supplier>
 */
class SupplierDuplicateFinder extends AbstractDuplicateFinder {
    public function __construct(
        EntityMatcher $matcher,
        private readonly SupplierMatchProfile $profile,
    ) {
        parent::__construct($matcher);
    }

    protected function profile(): MatchProfile {
        return $this->profile;
    }

    protected function fetchCandidates(Organization $organization): EloquentCollection {
        return $this->profile->candidates($organization)->withCount('purchaseOrders')->get();
    }

    /**
     * Ziel-Heuristik: gepflegte Lieferantennummer > mehr Bestellungen >
     * kleinere (ältere) ID. Der Datensatz mit der Einkaufs-Historie gewinnt,
     * damit Bestellbezüge nicht unnötig umgehängt werden.
     */
    protected function score(Model $model): array {
        $hasVendorNumber = trim((string) $model->vendor_number) !== '' ? 1 : 0;

        return [$hasVendorNumber, (int) ($model->purchase_orders_count ?? 0), -((int) $model->id)];
    }

    protected function dismissalModel(): string {
        return SupplierMergeDismissal::class;
    }

    protected function dismissalKeyColumns(): array {
        return ['supplier_low_id', 'supplier_high_id'];
    }
}
