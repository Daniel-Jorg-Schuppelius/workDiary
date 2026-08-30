<?php
/*
 * Created on   : Tue May 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : UserSpec.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Import\Specs;

use App\Enums\Import\{ImportEntity, ImportErrorCode};
use App\Models\{Organization, User};
use App\Services\Import\{ImportOutcome, ValidationIssue};
use Illuminate\Support\Str;
use Throwable;

/**
 * CSV-Spezifikation für den Benutzer-Import (MVP-049).
 *
 * Idempotenz-Schlüssel: `email` (platform-weit eindeutig). Beim Neuanlegen
 * wird ein zufälliges Initialpasswort gesetzt + `must_change_password = true`.
 * Bestehende Nutzer werden nur in den unkritischen Stammdaten aktualisiert
 * (kein Passwort-Reset, keine Organisations-Übernahme).
 */
class UserSpec extends AbstractEntitySpec {
    public function entity(): ImportEntity {
        return ImportEntity::Users;
    }

    public function columns(): array {
        return ['name', 'personnel_number', 'email', 'hourly_rate', 'internal_rate', 'home_address'];
    }

    public function requiredColumns(): array {
        return ['name', 'email'];
    }

    public function headerAliases(): array {
        return [
            'benutzer' => 'name',
            'mitarbeiter' => 'name',
            'personalnummer' => 'personnel_number',
            'personalnr' => 'personnel_number',
            'personalnr.' => 'personnel_number',
            'mitarbeiternummer' => 'personnel_number',
            'e-mail' => 'email',
            'stundensatz' => 'hourly_rate',
            'adresse' => 'home_address',
            'heimatadresse' => 'home_address',
        ];
    }

    public function normalize(array $row): array {
        $out = [];
        foreach ($this->columns() as $col) {
            $raw = $row[$col] ?? null;
            $out[$col] = match ($col) {
                'email' => ($v = $this->trimmedString($raw)) !== null ? mb_strtolower($v) : null,
                'hourly_rate', 'internal_rate' => $this->decimal($this->trimmedString($raw)),
                default => $this->trimmedString($raw),
            };
        }

        return $out;
    }

    public function validateRow(array $row, Organization $organization): array {
        $issues = [];
        if (($row['name'] ?? null) === null) {
            $issues[] = $this->requiredIssue('name');
        }
        if (($row['email'] ?? null) === null) {
            $issues[] = $this->requiredIssue('email');
        } elseif (! filter_var($row['email'], FILTER_VALIDATE_EMAIL)) {
            $issues[] = $this->formatIssue('email', (string) __('import.error.format.email'));
        }

        return $issues;
    }

    public function upsert(array $row, Organization $organization): array {
        try {
            // **Nur in der eigenen Organisation suchen** (Sicherheitsscan
            // 2026-08-23, S-07). `users.email` ist installationsweit eindeutig
            // und `User` trägt bewusst keinen OrganizationScope: die Suche
            // allein über die E-Mail traf deshalb auch Konten fremder
            // Mandanten — und überschrieb dort Name, Personalnummer und
            // beide Stundensätze.
            $existing = User::query()
                ->where('email', $row['email'])
                ->where('organization_id', $organization->id)
                ->first();

            if ($existing === null && $this->emailTakenElsewhere((string) $row['email'], $organization)) {
                // Bewusst dieselbe neutrale Meldung wie bei einer Kollision in
                // der eigenen Organisation: „Created" gegen „Updated" gegen
                // „Failed" wäre sonst ein Orakel dafür, welche E-Mail-Adressen
                // in dieser Installation ein Konto haben.
                return [ImportOutcome::Failed, new ValidationIssue(
                    ImportErrorCode::Unique,
                    'email',
                    (string) __('import.error.email_taken'),
                )];
            }

            if ($existing !== null) {
                $update = array_filter([
                    'name' => $row['name'] ?? null,
                    'personnel_number' => $row['personnel_number'] ?? null,
                    'hourly_rate' => $row['hourly_rate'] ?? null,
                    'internal_rate' => $row['internal_rate'] ?? null,
                    'home_address' => $row['home_address'] ?? null,
                ], static fn($v): bool => $v !== null);
                if ($update !== []) {
                    $existing->fill($update)->save();
                }

                return [ImportOutcome::Updated, null];
            }

            // Vollaudit 2026-07 (H8): Nutzerlimit auch im CSV-Import — der
            // Throwable-Catch unten macht daraus ImportOutcome::Failed mit Meldung.
            app(\App\Services\Licensing\LimitGuard::class)->ensureCanCreateUser($organization);

            User::create([
                'organization_id' => $organization->id,
                'name' => $row['name'],
                'personnel_number' => $row['personnel_number'] ?? null,
                'email' => $row['email'],
                'password' => Str::random(40),
                'must_change_password' => true,
                'is_new_system' => true,
                'hourly_rate' => $row['hourly_rate'] ?? null,
                'internal_rate' => $row['internal_rate'] ?? null,
                'home_address' => $row['home_address'] ?? null,
            ]);

            return [ImportOutcome::Created, null];
        } catch (Throwable $e) {
            return [
                ImportOutcome::Failed,
                new ValidationIssue(ImportErrorCode::Persist, null, $e->getMessage()),
            ];
        }
    }

    /** Existiert die E-Mail bereits — außerhalb dieser Organisation? */
    private function emailTakenElsewhere(string $email, Organization $organization): bool {
        return User::query()
            ->where('email', $email)
            ->where(function ($query) use ($organization): void {
                $query->whereNull('organization_id')
                    ->orWhere('organization_id', '!=', $organization->id);
            })
            ->exists();
    }

}
