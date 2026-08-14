<?php
/*
 * Created on   : Sat Aug 15 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ResolvesImportUsers.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Import\Specs\Concerns;

use App\Models\{ImportValueMapping, Organization, User};

/**
 * Benutzer-Auflösung der Zeitimport-Specs (Projektzeiten, Stempelungen,
 * Schichten): `user_email` ist die mappbare Spalte — die Preflight sammelt
 * unbekannte Adressen fürs Mapping-Formular (Rang 58, Muster Tag-Mapping),
 * der Import löst case-insensitiv über die Konto-E-Mail oder das persistierte
 * Benutzer-Mapping auf. Benutzer werden NIE blind angelegt; ignorierte
 * Adressen überspringen die Zeile.
 */
trait ResolvesImportUsers {
    /** Entität der nutzenden Spec ({@see \App\Services\Import\EntitySpec::entity()}). */
    abstract public function entity(): \App\Enums\Import\ImportEntity;

    public function mappableColumn(): string {
        return 'user_email';
    }

    /** @return list<string> */
    public function splitMappableValues(?string $raw): array {
        $value = trim((string) $raw);

        return $value === '' ? [] : [$value];
    }

    /**
     * Unbekannte Adressen (weder Konto-Treffer noch Mapping) — Datengrundlage
     * des Mapping-Formulars in der Preflight.
     *
     * @return list<string>
     */
    public function unresolvedMappableValues(Organization $organization, ?string $raw, string $entity): array {
        $unresolved = [];
        foreach ($this->splitMappableValues($raw) as $value) {
            if ($this->userByEmail($organization, $value) !== null) {
                continue;
            }
            if (ImportValueMapping::findFor((int) $organization->id, $entity, $value) !== null) {
                continue;
            }
            $unresolved[] = $value;
        }

        return $unresolved;
    }

    /**
     * Löst die Quell-E-Mail einer Zeile auf: Konto-Treffer (case-insensitiv)
     * → Benutzer-Mapping. Rückgabe {@see ImportValueMapping::KIND_IGNORE}
     * heißt „Zeile überspringen", null heißt unbekannt (fkMissing).
     */
    protected function resolveImportUser(Organization $organization, string $email): User|string|null {
        $user = $this->userByEmail($organization, $email);
        if ($user instanceof User) {
            return $user;
        }

        $mapping = ImportValueMapping::findFor((int) $organization->id, $this->entity()->value, $email);
        if ($mapping === null) {
            return null;
        }
        if ($mapping->target_kind === ImportValueMapping::KIND_IGNORE) {
            return ImportValueMapping::KIND_IGNORE;
        }
        if ($mapping->target_kind === ImportValueMapping::KIND_USER && $mapping->user_id !== null) {
            // Org-Guard: der Ziel-Benutzer muss weiterhin zur Organisation gehören.
            return User::query()
                ->where('organization_id', $organization->id)
                ->whereKey($mapping->user_id)
                ->first();
        }

        return null;
    }

    private function userByEmail(Organization $organization, string $email): ?User {
        return User::query()
            ->where('organization_id', $organization->id)
            ->whereRaw('LOWER(email) = ?', [ImportValueMapping::normalize($email)])
            ->first();
    }
}
