<?php
/*
 * Created on   : Thu Aug 20 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SupplierCredentialService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Supplier;

use App\Enums\Supplier\CredentialStatus;
use App\Models\Supplier;
use App\Models\Supplier\{SupplierCredential, SupplierCredentialType};
use App\Support\Setting;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Pflichtnachweise eines Lieferanten (Feature 117, MVP-606).
 *
 * Der Dienst beurteilt den Zustand — er sperrt nicht selbst. Die Sperre ist
 * eine Betriebsentscheidung (`procurement.credential_blocking`, Default
 * **Warnung**): Eine harte Sperre ab Werk legt Betriebe still, die ihre
 * Nachweise noch nicht erfasst haben. Man muss sie einschalten wollen.
 */
class SupplierCredentialService {
    /**
     * Zustand je Pflichttyp eines Lieferanten.
     *
     * @return Collection<int, array{type: SupplierCredentialType, credential: SupplierCredential|null, status: CredentialStatus}>
     */
    public function statusFor(Supplier $supplier): Collection {
        $today = CarbonImmutable::today();
        $organizationId = (int) $supplier->organization_id;

        $types = SupplierCredentialType::query()
            ->where('is_active', true)
            ->where('is_required_default', true)
            ->where(function ($query) use ($organizationId): void {
                // Katalog (NULL) + eigene Typen der Organisation.
                $query->whereNull('organization_id')->orWhere('organization_id', $organizationId);
            })
            ->orderBy('name')
            ->get();

        $credentials = SupplierCredential::query()
            ->where('supplier_id', $supplier->id)
            ->orderByDesc('valid_until')
            ->get()
            ->groupBy('supplier_credential_type_id');

        return $types->map(function (SupplierCredentialType $type) use ($credentials, $today): array {
            /** @var SupplierCredential|null $credential */
            $credential = $credentials->get($type->id)?->first();

            return [
                'type' => $type,
                'credential' => $credential,
                'status' => $this->statusOf($type, $credential, $today),
            ];
        });
    }

    /** Schlechteste Einzelstufe = Ampel des Lieferanten. */
    public function overallStatus(Supplier $supplier): CredentialStatus {
        $order = [
            CredentialStatus::Missing->value => 3,
            CredentialStatus::Expired->value => 3,
            CredentialStatus::Expiring->value => 2,
            CredentialStatus::Ok->value => 1,
        ];

        $worst = CredentialStatus::Ok;
        foreach ($this->statusFor($supplier) as $row) {
            if ($order[$row['status']->value] > $order[$worst->value]) {
                $worst = $row['status'];
            }
        }

        return $worst;
    }

    /**
     * Blockiert ein Nachweis die Beauftragung? Nur wenn die Organisation das
     * Sperren eingeschaltet hat — sonst bleiben dieselben Befunde eine reine
     * Warnung, siehe {@see self::missingReasons()}.
     *
     * @return list<string> Namen der blockierenden Nachweise (leer = frei)
     */
    public function blockingReasons(Supplier $supplier): array {
        return $this->blockingEnabled($supplier) ? $this->missingReasons($supplier) : [];
    }

    /**
     * Fehlende oder abgelaufene Pflichtnachweise — UNABHÄNGIG vom Sperrschalter.
     *
     * Die Sperre greift an der Bestellung; bei der Rechnungsfreigabe wäre sie
     * zu spät (die Leistung ist erbracht). Dort soll aber trotzdem jemand
     * sehen, dass der Nachweis fehlt — gerade bei Altfällen, deren Bestellung
     * vor der Einführung der Sperre entstand.
     *
     * @return list<string> Namen der betroffenen Nachweise (leer = vollständig)
     */
    public function missingReasons(Supplier $supplier): array {
        $reasons = [];
        foreach ($this->statusFor($supplier) as $row) {
            if ($row['status']->isBlocking() && $row['type']->blocks()) {
                $reasons[] = (string) $row['type']->name;
            }
        }

        return $reasons;
    }

    public function blockingEnabled(Supplier $supplier): bool {
        $organization = $supplier->organization;
        if ($organization !== null) {
            $value = data_get((array) ($organization->settings ?? []), 'procurement.credential_blocking');
            if ($value !== null) {
                return (bool) $value;
            }
        }

        return (bool) Setting::get('procurement.credential_blocking', config('procurement.credential_blocking', false));
    }

    private function statusOf(SupplierCredentialType $type, ?SupplierCredential $credential, CarbonImmutable $today): CredentialStatus {
        if ($credential === null) {
            return CredentialStatus::Missing;
        }
        if ($credential->valid_until === null) {
            // Unbefristeter Nachweis (z. B. eine Bescheinigung ohne Frist):
            // vorhanden ist vorhanden.
            return CredentialStatus::Ok;
        }
        if ($credential->valid_until->lessThan($today)) {
            return CredentialStatus::Expired;
        }
        if ($credential->valid_until->lessThanOrEqualTo($today->addDays(max(1, (int) $type->warn_days_before)))) {
            return CredentialStatus::Expiring;
        }

        return CredentialStatus::Ok;
    }
}
