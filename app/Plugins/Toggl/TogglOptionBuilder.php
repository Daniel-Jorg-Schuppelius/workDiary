<?php
/*
 * Created on   : Mon Jul 13 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TogglOptionBuilder.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Plugins\Toggl;

use App\Models\{Customer, Organization, Project, User};
use App\Support\Sqid;
use Illuminate\Support\Collection;

/**
 * Baut die Dropdown-/Auswahloptionen der Toggl-Import-Masken (Kunden,
 * System-Benutzer, Projekte, Workspace-Benutzer) und löst die im Formular
 * getroffene Auswahl (Sqids) wieder in IDs auf. Aus dem TogglController
 * extrahiert (Refactoring Welle 2, B6c).
 */
class TogglOptionBuilder {
    /**
     * @param  Collection<int, Customer>  $customers
     * @return array<int, array{sqid: string, id: int, label: string}>
     */
    public function customerOptions(Collection $customers): array {
        return $customers->map(fn(Customer $c): array => [
            'sqid' => $c->sqid,
            'id' => (int) $c->id,
            'label' => (string) ($c->company ?: $c->name),
        ])->all();
    }

    /**
     * Kunden der Organisation als Dropdown-Optionen (für die Workspace-Import-Modi).
     *
     * @return array<int, array{sqid: string, id: int, label: string}>
     */
    public function customerSelectOptions(): array {
        return $this->customerOptions(
            Customer::query()->orderBy('name')->get(['id', 'name', 'company'])
        );
    }

    /**
     * Benutzer der Organisation als Dropdown-Optionen (für die explizite
     * Benutzer-Zuordnung der Toggl-Workspace-Benutzer).
     *
     * @return array<int, array{sqid: string, label: string}>
     */
    public function userSelectOptions(): array {
        return User::inCurrentOrganization()
            ->orderBy('name')
            ->get(['id', 'name', 'email'])
            ->map(fn(User $u): array => [
                'sqid' => $u->sqid,
                'label' => trim((string) $u->name) !== ''
                    ? $u->name . ' (' . $u->email . ')'
                    : (string) $u->email,
            ])->all();
    }

    /**
     * @param  Collection<int, Project>  $projects
     * @return array<int, array{sqid: string, customer_id: int|null, name: string}>
     */
    public function projectOptions(Collection $projects): array {
        return $projects->map(fn(Project $p): array => [
            'sqid' => $p->sqid,
            'customer_id' => $p->customer_id !== null ? (int) $p->customer_id : null,
            'name' => (string) $p->name,
        ])->all();
    }

    /**
     * Sammelt distinkte Toggl-Workspace-Benutzer (über alle Workspaces hinweg,
     * dedupliziert per E-Mail) für die Zuordnungs-Oberfläche.
     *
     * @param  array<string, array{email: string, name: string}>  $bucket  (per Referenz, Schlüssel = lower(email))
     * @param  array<int, array{email: string, name: string, timezone?: ?string}>  $users
     */
    public function collectTogglUsers(array &$bucket, array $users): void {
        foreach ($users as $u) {
            $email = trim($u['email']);
            if ($email === '') {
                continue;
            }
            $key = mb_strtolower($email);
            $bucket[$key] ??= ['email' => $email, 'name' => trim($u['name']) ?: $email];
        }
    }

    /**
     * @param  array<string, array{email: string, name: string}>  $bucket
     * @return array<int, array{email: string, name: string}>
     */
    public function sortTogglUsers(array $bucket): array {
        ksort($bucket);

        return array_values($bucket);
    }

    /** Optionale Kunden-Sqid → ID (null bei leerer Auswahl, z. B. „neuer Kunde"). */
    public function optionalCustomerId(?string $sqid): ?int {
        $sqid = trim((string) $sqid);

        return $sqid === '' ? null : $this->decodeId(Customer::class, $sqid);
    }

    /**
     * Baut die explizite Benutzer-Zuordnung aus der UI (Toggl-E-Mail → System-User).
     * Leere Auswahlen und Benutzer fremder Organisationen werden ignoriert.
     *
     * @param  array<string, string|null>  $raw  E-Mail → User-Sqid
     * @return array<string, int>  lower(email) → User-ID
     */
    public function buildUserMap(array $raw, Organization $organization): array {
        $map = [];
        foreach ($raw as $email => $sqid) {
            $email = trim((string) $email);
            $sqid = trim((string) $sqid);
            if ($email === '' || $sqid === '') {
                continue;
            }
            $userId = Sqid::decode(User::class, $sqid);
            if ($userId === null) {
                continue;
            }
            $user = User::query()->whereKey($userId)->first();
            if ($user instanceof User && (int) $user->organization_id === (int) $organization->id) {
                $map[mb_strtolower($email)] = (int) $user->id;
            }
        }

        return $map;
    }

    /**
     * Dekodiert eine Sqid (oder akzeptiert eine numerische ID) zu einer Model-ID.
     *
     * @param  class-string  $model
     */
    public function decodeId(string $model, mixed $raw): int {
        $id = Sqid::decode($model, (string) $raw);
        if ($id === null && is_numeric($raw)) {
            $id = (int) $raw;
        }
        abort_if($id === null, 422, (string) __('Ungültige Auswahl.'));

        return $id;
    }
}
