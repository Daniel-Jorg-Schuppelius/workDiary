<?php
/*
 * Created on   : Sun Nov 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_04_29_100000_add_mirror_number_indexes.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Indizes für die Dublettenregel zwischen lokalen Rechnungen und gespiegelten Belegen (MVP-1794):
 * ohne sie prüft die Erlösregel je Spiegelbeleg alle Rechnungen der Organisation und umgekehrt.
 */
return new class extends Migration {
    /** @var array<string, array{0: string, 1: string}> Tabelle → [Spalte, Indexname] */
    private const array INDEXES = [
        'invoices' => ['external_number', 'invoices_org_external_number_idx'],
        'lexoffice_vouchers' => ['voucher_number', 'lex_voucher_org_number_idx'],
        'accounting_vouchers' => ['voucher_number', 'acc_voucher_org_number_idx'],
    ];

    public function up(): void {
        foreach (self::INDEXES as $table => [$column, $index]) {
            if (! Schema::hasTable($table) || Schema::hasIndex($table, $index)) {
                continue;
            }
            Schema::table($table, static function (Blueprint $t) use ($column, $index): void {
                $t->index(['organization_id', $column], $index);
            });
        }
    }

    public function down(): void {
        foreach (self::INDEXES as $table => [, $index]) {
            if (! Schema::hasTable($table) || ! Schema::hasIndex($table, $index)) {
                continue;
            }
            Schema::table($table, static function (Blueprint $t) use ($index): void {
                $t->dropIndex($index);
            });
        }
    }
};
