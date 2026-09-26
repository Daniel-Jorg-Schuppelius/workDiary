<?php
/*
 * Created on   : Fri Sep 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_02_25_220000_create_lexoffice_voucher_categories_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** MVP-905: Buchungskategorien und Kategoriezeilen der Lexoffice-Einkaufsbelege. */
return new class extends Migration {
    public function up(): void {
        Schema::create('lexoffice_posting_categories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('external_id', 64);
            $table->string('name', 255);
            $table->string('kind', 20);
            $table->string('group_name', 255)->nullable();
            $table->timestamps();
            $table->unique(['organization_id', 'external_id'], 'lex_posting_cat_org_ext_uniq');
        });

        Schema::create('lexoffice_voucher_categories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('voucher_id')->constrained('lexoffice_vouchers', indexName: 'lex_voucher_cat_voucher_fk')->cascadeOnDelete();
            $table->unsignedSmallInteger('position');
            $table->string('category_external_id', 64)->nullable();
            $table->decimal('net_amount', 12, 2);
            $table->string('currency', 3);
            $table->decimal('tax_rate', 5, 2)->nullable();
            $table->timestamps();
            $table->unique(['voucher_id', 'position'], 'lex_voucher_cat_pos_uniq');
            $table->index(['organization_id', 'category_external_id'], 'lex_voucher_cat_org_cat_idx');
        });

        Schema::table('lexoffice_vouchers', function (Blueprint $table): void {
            $table->timestamp('categories_synced_at')->nullable();
        });
    }

    public function down(): void {
        Schema::table('lexoffice_vouchers', function (Blueprint $table): void {
            $table->dropColumn('categories_synced_at');
        });
        Schema::dropIfExists('lexoffice_voucher_categories');
        Schema::dropIfExists('lexoffice_posting_categories');
    }
};
