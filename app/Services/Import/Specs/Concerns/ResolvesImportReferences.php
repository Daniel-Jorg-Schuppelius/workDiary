<?php
/*
 * Created on   : Tue Aug 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ResolvesImportReferences.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Import\Specs\Concerns;

use App\Enums\Import\ImportErrorCode;
use App\Models\{Article, Asset, Customer, Organization, Project, Supplier};
use App\Services\Import\ValidationIssue;

/**
 * Auflösung fachlicher Schlüssel (Kunden-/Lieferanten-/Projekt-/Asset-/
 * Artikelnummer) auf Datensätze der Organisation — immer org-gescopt, nie
 * über rohe IDs (MVP-707).
 */
trait ResolvesImportReferences {
    protected function customerByNumber(Organization $organization, ?string $number): ?Customer {
        if ($number === null || $number === '') {
            return null;
        }

        return Customer::query()
            ->where('organization_id', $organization->id)
            ->where('number', $number)
            ->first();
    }

    /**
     * Kunde über exakten Namen — nur bei EINDEUTIGEM Treffer.
     */
    protected function customerByUniqueName(Organization $organization, ?string $name): ?Customer {
        if ($name === null || $name === '') {
            return null;
        }

        $matches = Customer::query()
            ->where('organization_id', $organization->id)
            ->where('name', $name)
            ->limit(2)
            ->get();

        return $matches->count() === 1 ? $matches->first() : null;
    }

    protected function supplierByNumber(Organization $organization, ?string $number): ?Supplier {
        if ($number === null || $number === '') {
            return null;
        }

        return Supplier::query()
            ->where('organization_id', $organization->id)
            ->where('number', $number)
            ->first();
    }

    protected function projectByNumber(Organization $organization, ?string $number): ?Project {
        if ($number === null || $number === '') {
            return null;
        }

        return Project::query()
            ->where('organization_id', $organization->id)
            ->where('number', $number)
            ->first();
    }

    protected function assetByNumber(Organization $organization, ?string $assetNo): ?Asset {
        if ($assetNo === null || $assetNo === '') {
            return null;
        }

        return Asset::query()
            ->where('organization_id', $organization->id)
            ->where('asset_no', $assetNo)
            ->first();
    }

    protected function articleByNumber(Organization $organization, ?string $number): ?Article {
        if ($number === null || $number === '') {
            return null;
        }

        return Article::query()
            ->where('organization_id', $organization->id)
            ->where('number', $number)
            ->first();
    }

    /**
     * Verweisfehler mit übersetzter Meldung (`import.error.fkMissing.<key>`).
     */
    protected function fkIssue(string $field, string $key, string $value): ValidationIssue {
        return new ValidationIssue(
            ImportErrorCode::FkMissing,
            $field,
            (string) __('import.error.fkMissing.' . $key, ['number' => $value, 'value' => $value]),
        );
    }
}
