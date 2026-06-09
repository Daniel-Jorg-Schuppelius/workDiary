<?php
/*
 * Created on   : Tue Jun 09 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_08_13_000028_add_license_columns_to_organizations.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};
use Illuminate\Support\Str;

/**
 * Org-gebundene Lizenzen: jede Organisation traegt ihren signierten Lizenz-
 * schluessel selbst (`license_key`) und eine stabile Bindungs-ID (`license_uid`),
 * gegen die der Lizenz-Issuer signiert. Kurze, explizite Indexnamen (MySQL-64-Limit).
 */
return new class extends Migration {
    public function up(): void {
        Schema::table('organizations', function (Blueprint $table): void {
            $table->text('license_key')->nullable()->after('plan');
            $table->string('license_uid', 36)->nullable()->after('license_key');
        });

        // Bestands-Orgs eine Bindungs-ID geben.
        DB::table('organizations')->whereNull('license_uid')->orderBy('id')->each(function (object $org): void {
            DB::table('organizations')->where('id', $org->id)->update(['license_uid' => (string) Str::uuid()]);
        });

        Schema::table('organizations', function (Blueprint $table): void {
            $table->unique('license_uid', 'orgs_license_uid_unique');
        });
    }

    public function down(): void {
        Schema::table('organizations', function (Blueprint $table): void {
            $table->dropUnique('orgs_license_uid_unique');
            $table->dropColumn(['license_key', 'license_uid']);
        });
    }
};
