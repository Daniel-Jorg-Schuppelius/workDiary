<?php
/*
 * Created on   : Fri Aug 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ChartOfAccountsService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Enums\Finance\{AccountType, BalanceSide, EuerCategory};
use App\Models\Accounting\{AccountingAccount, AccountingEntryLine};
use App\Models\Organization;
use App\Support\Toolkit\CsvFacade;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Kontenplan (Feature 125, MVP-672).
 *
 * Der Import ist bewusst ein Mapping-Import und kein mitgelieferter
 * Standardkontenrahmen: Welcher Kontenrahmen ausgeliefert werden darf, ist
 * lizenzrechtlich offen (Risiko 1 des Feature-Dokuments). Frei
 * konfigurierbare Konten und CSV-Import funktionieren unabhängig davon.
 */
class ChartOfAccountsService {
    /** Erwartete CSV-Spalten (Kopfzeile, unabhängig von der Reihenfolge). */
    public const COLUMNS = ['number', 'name', 'type', 'normal_balance', 'is_open_item', 'datev_account'];

    /** @param array<string, mixed> $data */
    public function create(Organization $organization, array $data): AccountingAccount {
        $type = $data['type'] instanceof AccountType ? $data['type'] : AccountType::from((string) $data['type']);

        return AccountingAccount::query()->create([
            'organization_id' => $organization->id,
            'number' => trim((string) $data['number']),
            'name' => trim((string) $data['name']),
            'type' => $type,
            'normal_balance' => $this->balanceSide($data['normal_balance'] ?? null) ?? $type->normalBalance(),
            'is_open_item' => (bool) ($data['is_open_item'] ?? false),
            'is_bank' => (bool) ($data['is_bank'] ?? false),
            'is_cash' => (bool) ($data['is_cash'] ?? false),
            'is_clearing' => (bool) ($data['is_clearing'] ?? false),
            'euer_category' => $this->euerCategory($data['euer_category'] ?? null),
            'deductible_percent' => number_format((float) ($data['deductible_percent'] ?? 100), 2, '.', ''),
            'default_tax_code_id' => $data['default_tax_code_id'] ?? null,
            'datev_account' => $data['datev_account'] ?? null,
            'is_active' => (bool) ($data['is_active'] ?? true),
            'description' => $data['description'] ?? null,
        ]);
    }

    /** Leere Kategorie bleibt leer: Sie ist ein Klärungsfall, kein Standardwert. */
    private function euerCategory(mixed $value): ?EuerCategory {
        if ($value instanceof EuerCategory) {
            return $value;
        }

        return is_string($value) && $value !== '' ? EuerCategory::from($value) : null;
    }

    /**
     * Konto stilllegen statt löschen, sobald darauf gebucht wurde — sonst
     * verweist die Historie auf eine Nummer, die niemand mehr auflösen kann.
     */
    public function deactivate(AccountingAccount $account): AccountingAccount {
        $account->update(['is_active' => false]);

        return $account->refresh();
    }

    /** Löschen ist nur erlaubt, solange das Konto unbenutzt ist. */
    public function delete(AccountingAccount $account): void {
        if ($this->isInUse($account)) {
            throw ValidationException::withMessages([
                'account' => (string) __('accounting.ledger.error.account_in_use'),
            ]);
        }

        $account->delete();
    }

    public function isInUse(AccountingAccount $account): bool {
        return AccountingEntryLine::query()
            ->where('accounting_account_id', $account->id)
            ->exists();
    }

    /**
     * CSV-Import des Kontenplans. Bestehende Nummern werden aktualisiert,
     * nie doppelt angelegt; fehlerhafte Zeilen werden gemeldet statt still
     * übersprungen.
     *
     * @return array{imported: int, updated: int, errors: list<string>}
     */
    public function importCsv(Organization $organization, string $absolutePath): array {
        $imported = 0;
        $updated = 0;
        $errors = [];

        DB::transaction(function () use ($organization, $absolutePath, &$imported, &$updated, &$errors): void {
            foreach (CsvFacade::streamAssoc($absolutePath) as $lineNumber => $row) {
                $number = trim((string) ($row['number'] ?? ''));
                $name = trim((string) ($row['name'] ?? ''));
                $type = AccountType::tryFrom(strtolower(trim((string) ($row['type'] ?? ''))));

                if ($number === '' || $name === '' || $type === null) {
                    $errors[] = (string) __('accounting.ledger.import.line_invalid', ['line' => (string) $lineNumber]);

                    continue;
                }

                $attributes = [
                    'name' => $name,
                    'type' => $type,
                    'normal_balance' => $this->balanceSide($row['normal_balance'] ?? null) ?? $type->normalBalance(),
                    'is_open_item' => $this->flag($row['is_open_item'] ?? null),
                    'datev_account' => trim((string) ($row['datev_account'] ?? '')) ?: null,
                ];

                // Optionale Spalten: fehlt die Kategorie, bleibt sie leer und
                // taucht als Klärungsfall in der EÜR-Vorschau auf.
                if (array_key_exists('euer_category', $row)) {
                    $attributes['euer_category'] = EuerCategory::tryFrom(strtolower(trim((string) $row['euer_category'])));
                }
                if (array_key_exists('deductible_percent', $row) && trim((string) $row['deductible_percent']) !== '') {
                    $attributes['deductible_percent'] = number_format((float) $row['deductible_percent'], 2, '.', '');
                }

                $account = AccountingAccount::query()
                    ->where('organization_id', $organization->id)
                    ->where('number', $number)
                    ->first();

                if ($account instanceof AccountingAccount) {
                    $account->update($attributes);
                    $updated++;

                    continue;
                }

                AccountingAccount::query()->create($attributes + [
                    'organization_id' => $organization->id,
                    'number' => $number,
                    'is_active' => true,
                ]);
                $imported++;
            }
        });

        return ['imported' => $imported, 'updated' => $updated, 'errors' => $errors];
    }

    private function balanceSide(mixed $value): ?BalanceSide {
        if ($value instanceof BalanceSide) {
            return $value;
        }

        $normalized = strtolower(trim((string) $value));

        return $normalized === '' ? null : BalanceSide::tryFrom($normalized);
    }

    private function flag(mixed $value): bool {
        return in_array(strtolower(trim((string) $value)), ['1', 'ja', 'yes', 'true', 'x'], true);
    }
}
