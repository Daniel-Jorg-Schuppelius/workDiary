<?php
/*
 * Created on   : Thu Aug 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_02_05_100000_add_phone_search_keys_to_contacts.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use App\Support\PhoneSearchKey;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};

/**
 * Normalisierter Rufnummern-Suchschlüssel (Folgepunkt aus Audit-Welle 2.4).
 *
 * Der bisherige Vorfilter suchte die letzten sieben Ziffern als
 * zusammenhängende Zeichenkette. Stehen im gespeicherten Wert Trennzeichen
 * INNERHALB dieser sieben Ziffern („0511 / 123 456 78"), fand er den
 * Datensatz nicht — im Alltag als „Anrufer wird nicht erkannt" sichtbar.
 *
 * Die neuen Spalten tragen die Nummer in E.164 und werden beim Speichern
 * gepflegt ({@see \App\Models\Concerns\HasPhoneSearchKeys}). Der Bestand wird
 * hier einmal nachgezogen — in Blöcken, weil die Normalisierung in PHP läuft
 * und nicht als SQL-Ausdruck existiert.
 */
return new class extends Migration {
    /** @var array<string, string> Tabelle → Index-Präfix (Namen DB-weit eindeutig, max. 64) */
    private const TABLES = [
        'customers' => 'cust',
        'foreign_customers' => 'fcust',
        'suppliers' => 'supp',
    ];

    public function up(): void {
        foreach (self::TABLES as $table => $prefix) {
            Schema::table($table, function (Blueprint $blueprint) use ($prefix): void {
                $blueprint->string('phone_e164', 24)->nullable()->after('mobile');
                $blueprint->string('mobile_e164', 24)->nullable()->after('phone_e164');
                $blueprint->index(['organization_id', 'phone_e164'], $prefix . '_org_phone_e164_idx');
                $blueprint->index(['organization_id', 'mobile_e164'], $prefix . '_org_mobile_e164_idx');
            });

            $this->backfill($table);
        }
    }

    public function down(): void {
        foreach (self::TABLES as $table => $prefix) {
            Schema::table($table, function (Blueprint $blueprint) use ($prefix): void {
                $blueprint->dropIndex($prefix . '_org_phone_e164_idx');
                $blueprint->dropIndex($prefix . '_org_mobile_e164_idx');
                $blueprint->dropColumn(['phone_e164', 'mobile_e164']);
            });
        }
    }

    /** Bestand nachziehen; nur Zeilen mit Nummer, blockweise. */
    private function backfill(string $table): void {
        DB::table($table)
            ->select(['id', 'phone', 'mobile'])
            ->where(function ($query): void {
                $query->whereNotNull('phone')->orWhereNotNull('mobile');
            })
            ->orderBy('id')
            ->chunk(500, function ($rows) use ($table): void {
                foreach ($rows as $row) {
                    $phone = self::normalize((string) ($row->phone ?? ''));
                    $mobile = self::normalize((string) ($row->mobile ?? ''));
                    if ($phone === null && $mobile === null) {
                        continue;
                    }

                    DB::table($table)->where('id', $row->id)->update([
                        'phone_e164' => $phone,
                        'mobile_e164' => $mobile,
                    ]);
                }
            });
    }

    /** Dieselbe Regel wie beim Speichern — sonst liefen Bestand und Neuzugang auseinander. */
    private static function normalize(string $value): ?string {
        return PhoneSearchKey::of($value);
    }
};
