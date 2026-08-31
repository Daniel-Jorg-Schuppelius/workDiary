<?php
/*
 * Created on   : Sun Aug 31 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_02_19_110500_encrypt_bank_and_health_columns.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{Crypt, DB, Schema};

/**
 * Verschlüsselt Bank- und Gesundheitsdaten at-rest (Sicherheitsscan
 * 2026-08-23, S-21).
 *
 * **Bankdaten** waren nie als Klartext gedacht: `contact_bank_accounts.iban`
 * und `sepa_mandates.iban` liegen längst verschlüsselt. Die Spalten
 * `customers.bank_iban` / `suppliers.bank_iban` sind eine Projektion des
 * primären Bankkontos — der ContactDetailsProjectionObserver schrieb den
 * entschlüsselten Wert flach zurück und hob die Verschlüsselung damit auf.
 *
 * **Gesundheitsdaten** (`sick_leaves`) sind Art.-9-Daten und lagen bisher
 * ungeschützt: AU-Nummer, Arztname und die Notiz, in der erfahrungsgemäß auch
 * Diagnosen landen.
 *
 * Die Spalten werden auf `text` erweitert, weil der Chiffretext ein Vielfaches
 * des Klartexts misst und MariaDB im Strict-Modus sonst abschneidet statt zu
 * warnen. Leere Zeichenketten werden zu `null` normalisiert: ein
 * verschlüsseltes "" lässt sich beim Lesen nicht entschlüsseln und wirft.
 */
return new class extends Migration {
    /** Tabelle => Spalten. */
    private const COLUMNS = [
        'customers' => ['bank_iban', 'bank_bic', 'bank_account_holder'],
        'suppliers' => ['bank_iban', 'bank_bic', 'bank_account_holder'],
        'sick_leaves' => ['au_number', 'doctor_name', 'note'],
    ];

    public function up(): void {
        foreach (self::COLUMNS as $table => $columns) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint) use ($table, $columns): void {
                foreach ($columns as $column) {
                    if (Schema::hasColumn($table, $column)) {
                        $blueprint->text($column)->nullable()->change();
                    }
                }
            });

            $this->transform($table, $columns, static fn(string $value): string => Crypt::encryptString($value));
        }
    }

    public function down(): void {
        foreach (self::COLUMNS as $table => $columns) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            $this->transform($table, $columns, static function (string $value): string {
                try {
                    return Crypt::decryptString($value);
                } catch (\Throwable) {
                    // Bereits Klartext (teilweise gelaufene Migration) → so lassen.
                    return $value;
                }
            });

            Schema::table($table, function (Blueprint $blueprint) use ($table, $columns): void {
                foreach ($columns as $column) {
                    if (! Schema::hasColumn($table, $column)) {
                        continue;
                    }
                    $column === 'note'
                        ? $blueprint->text($column)->nullable()->change()
                        : $blueprint->string($column, 255)->nullable()->change();
                }
            });
        }
    }

    /**
     * @param  list<string>  $columns
     * @param  callable(string): string  $convert
     */
    private function transform(string $table, array $columns, callable $convert): void {
        $present = array_values(array_filter($columns, static fn(string $c): bool => Schema::hasColumn($table, $c)));
        if ($present === []) {
            return;
        }

        DB::table($table)
            ->select(array_merge(['id'], $present))
            ->orderBy('id')
            ->chunk(500, function ($rows) use ($table, $present, $convert): void {
                foreach ($rows as $row) {
                    $update = [];
                    foreach ($present as $column) {
                        $value = $row->{$column};
                        if ($value === null) {
                            continue;
                        }
                        if (trim((string) $value) === '') {
                            $update[$column] = null;

                            continue;
                        }
                        $update[$column] = $convert((string) $value);
                    }

                    if ($update !== []) {
                        DB::table($table)->where('id', $row->id)->update($update);
                    }
                }
            });
    }
};
