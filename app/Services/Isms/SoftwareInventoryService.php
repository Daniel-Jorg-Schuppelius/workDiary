<?php
/*
 * Created on   : Thu Jun 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SoftwareInventoryService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Services\Isms;

use App\Enums\Isms\SupportStatus;
use App\Models\Isms\{IsmsSoftwareInstallation, IsmsSoftwareProduct};
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Domain-Service organisationsbezogenes Softwareinventar
 * (Feature 044, MVP 1, Ebene 1).
 *
 * Geschäftsregeln (bewusst schlank):
 * - Liegt eol_on in der Vergangenheit, wird support_status beim Speichern
 *   automatisch auf endOfLife gesetzt (Produkt anlegen UND aktualisieren).
 * - Ein Produkt mit Installationen ist nicht löschbar — erst die
 *   Installationen entfernen (Fehlermeldung statt Kaskade, damit kein
 *   Inventarbestand „still" verschwindet).
 *
 * Audit läuft über den Auditable-Trait (created/updated/deleted) plus
 * gezielte audit()-Events beim Löschen — analog RiskService/ScopeService.
 */
class SoftwareInventoryService {
    /**
     * Legt ein Softwareprodukt an (EOL-Automatik inklusive).
     *
     * @param  array<string, mixed>  $attributes
     */
    public function createProduct(User $creator, array $attributes): IsmsSoftwareProduct {
        return DB::transaction(fn(): IsmsSoftwareProduct => IsmsSoftwareProduct::query()->create([
            'organization_id' => $creator->organization_id,
            'name' => $attributes['name'],
            'vendor' => $attributes['vendor'] ?? null,
            'product_version' => $attributes['product_version'] ?? null,
            'category' => $attributes['category'] ?? null,
            'owner_user_id' => $attributes['owner_user_id'] ?? null,
            'support_status' => $this->effectiveSupportStatus($attributes['support_status'] ?? null, $attributes['eol_on'] ?? null),
            'eol_on' => $attributes['eol_on'] ?? null,
            'notes' => $attributes['notes'] ?? null,
        ]));
    }

    /**
     * Aktualisiert ein Softwareprodukt (EOL-Automatik inklusive).
     *
     * @param  array<string, mixed>  $attributes
     */
    public function updateProduct(IsmsSoftwareProduct $product, User $actor, array $attributes): IsmsSoftwareProduct {
        unset($actor);

        return DB::transaction(function () use ($product, $attributes): IsmsSoftwareProduct {
            $eolOn = array_key_exists('eol_on', $attributes) ? $attributes['eol_on'] : $product->eol_on;

            $product->update([
                'name' => $attributes['name'] ?? $product->name,
                'vendor' => array_key_exists('vendor', $attributes) ? $attributes['vendor'] : $product->vendor,
                'product_version' => array_key_exists('product_version', $attributes) ? $attributes['product_version'] : $product->product_version,
                'category' => array_key_exists('category', $attributes) ? $attributes['category'] : $product->category,
                'owner_user_id' => array_key_exists('owner_user_id', $attributes) ? $attributes['owner_user_id'] : $product->owner_user_id,
                'support_status' => $this->effectiveSupportStatus($attributes['support_status'] ?? $product->support_status->value, $eolOn),
                'eol_on' => $eolOn,
                'notes' => array_key_exists('notes', $attributes) ? $attributes['notes'] : $product->notes,
            ]);

            return $product;
        });
    }

    /**
     * Soft-Delete eines Produkts — nur ohne Installationen.
     *
     * @throws ValidationException wenn noch Installationen existieren
     */
    public function deleteProduct(IsmsSoftwareProduct $product, User $actor): void {
        if ($product->installations()->exists()) {
            throw ValidationException::withMessages([
                'product' => __('isms.error.product_has_installations'),
            ]);
        }

        DB::transaction(function () use ($product, $actor): void {
            $product->audit('isms.software_product.deleted', ['actor_user_id' => $actor->id]);
            $product->delete();
        });
    }

    /**
     * Legt eine Installation zu einem Produkt an.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function createInstallation(IsmsSoftwareProduct $product, User $creator, array $attributes): IsmsSoftwareInstallation {
        return DB::transaction(fn(): IsmsSoftwareInstallation => IsmsSoftwareInstallation::query()->create([
            'organization_id' => $creator->organization_id,
            'isms_software_product_id' => $product->id,
            'installed_version' => $attributes['installed_version'] ?? null,
            'asset_ref' => $attributes['asset_ref'] ?? null,
            'location' => $attributes['location'] ?? null,
            'notes' => $attributes['notes'] ?? null,
        ]));
    }

    /**
     * Aktualisiert eine Installation.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function updateInstallation(IsmsSoftwareInstallation $installation, User $actor, array $attributes): IsmsSoftwareInstallation {
        unset($actor);

        return DB::transaction(function () use ($installation, $attributes): IsmsSoftwareInstallation {
            $installation->update([
                'installed_version' => array_key_exists('installed_version', $attributes) ? $attributes['installed_version'] : $installation->installed_version,
                'asset_ref' => array_key_exists('asset_ref', $attributes) ? $attributes['asset_ref'] : $installation->asset_ref,
                'location' => array_key_exists('location', $attributes) ? $attributes['location'] : $installation->location,
                'notes' => array_key_exists('notes', $attributes) ? $attributes['notes'] : $installation->notes,
            ]);

            return $installation;
        });
    }

    /** Soft-Delete einer Installation. */
    public function deleteInstallation(IsmsSoftwareInstallation $installation, User $actor): void {
        DB::transaction(function () use ($installation, $actor): void {
            $installation->audit('isms.software_installation.deleted', ['actor_user_id' => $actor->id]);
            $installation->delete();
        });
    }

    /**
     * EOL-Automatik: liegt eol_on in der Vergangenheit (vor heute), ist der
     * effektive Support-Status immer endOfLife — unabhängig von der Eingabe.
     */
    private function effectiveSupportStatus(mixed $status, mixed $eolOn): string {
        $value = is_string($status) && SupportStatus::tryFrom($status) !== null
            ? $status
            : SupportStatus::Unknown->value;

        $eol = match (true) {
            $eolOn instanceof \DateTimeInterface => Carbon::instance($eolOn),
            is_string($eolOn) && $eolOn !== '' => Carbon::parse($eolOn),
            default => null,
        };

        if ($eol !== null && $eol->startOfDay()->lt(now()->startOfDay())) {
            return SupportStatus::EndOfLife->value;
        }

        return $value;
    }
}
