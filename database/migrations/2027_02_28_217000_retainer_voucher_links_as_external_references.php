<?php
/*
 * Created on   : Tue Sep 29 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_02_28_217000_retainer_voucher_links_as_external_references.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};

/**
 * MVP-1027: Der in Lexoffice geführte Pauschalbeleg eines Monats hängt als
 * ExternalReference (Monat → Beleg-ID, Typ `retainer_voucher`) am Monat statt
 * als Fremdschlüssel auf die Plugin-Tabelle `lexoffice_vouchers`.
 */
return new class extends Migration {
    private const PLUGIN = 'lexoffice';

    private const TYPE = 'retainer_voucher';

    private const MORPH = 'customer_billing_statements';

    public function up(): void {
        $now = now();
        DB::table('customer_billing_statements as s')
            ->join('lexoffice_vouchers as v', 'v.id', '=', 's.lexoffice_voucher_id')
            ->whereNotNull('s.lexoffice_voucher_id')
            ->orderBy('s.id')
            ->select(['s.id', 's.organization_id', 'v.external_id'])
            ->chunkById(500, static function ($rows) use ($now): void {
                DB::table('external_references')->insertOrIgnore($rows->map(static fn (object $row): array => [
                    'organization_id' => $row->organization_id,
                    'plugin_id' => self::PLUGIN,
                    'external_type' => self::TYPE,
                    'referenceable_type' => self::MORPH,
                    'referenceable_id' => $row->id,
                    'external_id' => $row->external_id,
                    'synced_at' => $now,
                    'created_at' => $now,
                    'updated_at' => $now,
                ])->all());
            }, 's.id', 'id');

        Schema::table('customer_billing_statements', function (Blueprint $table): void {
            // SQLite entfernt Fremdschlüssel nur über die Spalte, MySQL über den vergebenen Namen.
            $table->dropForeign(Schema::getConnection()->getDriverName() === 'sqlite' ? ['lexoffice_voucher_id'] : 'fk_cbs_lexoffice_voucher');
        });
        Schema::table('customer_billing_statements', function (Blueprint $table): void {
            $table->dropUnique('uq_cbs_lexoffice_voucher');
        });
        Schema::table('customer_billing_statements', function (Blueprint $table): void {
            $table->dropColumn('lexoffice_voucher_id');
        });
    }

    public function down(): void {
        Schema::table('customer_billing_statements', function (Blueprint $table): void {
            $table->foreignId('lexoffice_voucher_id')->nullable()
                ->constrained('lexoffice_vouchers', indexName: 'fk_cbs_lexoffice_voucher')->nullOnDelete();
            $table->unique('lexoffice_voucher_id', 'uq_cbs_lexoffice_voucher');
        });

        $refs = DB::table('external_references')
            ->where('plugin_id', self::PLUGIN)->where('external_type', self::TYPE)->where('referenceable_type', self::MORPH)
            ->get(['organization_id', 'referenceable_id', 'external_id']);
        foreach ($refs as $ref) {
            $voucherId = DB::table('lexoffice_vouchers')->where('organization_id', $ref->organization_id)->where('external_id', $ref->external_id)->value('id');
            if ($voucherId !== null) {
                DB::table('customer_billing_statements')->where('id', $ref->referenceable_id)->update(['lexoffice_voucher_id' => $voucherId]);
            }
        }
        DB::table('external_references')
            ->where('plugin_id', self::PLUGIN)->where('external_type', self::TYPE)->where('referenceable_type', self::MORPH)
            ->delete();
    }
};
