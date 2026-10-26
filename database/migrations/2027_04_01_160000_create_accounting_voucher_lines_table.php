<?php
/*
 * Created on   : Mon Oct 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_04_01_160000_create_accounting_voucher_lines_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Feature 185 (MVP-1424): Positionen im allgemeinen Belegspiegel — eine
 * Quelle für alle Buchhaltungsprogramme des Spiegels (sevDesk, easybill,
 * Invoice Ninja, Akaunting, InvoicePlane). `lines_synced_at` am Beleg sagt,
 * ob die Positionen schon gelesen wurden (Einzelabruf bei sevDesk).
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('accounting_voucher_lines', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $t->foreignId('voucher_id')->constrained('accounting_vouchers')->cascadeOnDelete();
            $t->unsignedSmallInteger('position');
            $t->string('external_article_id', 128)->nullable();
            $t->string('article_ref', 64)->nullable();
            $t->string('name', 255);
            $t->text('description')->nullable();
            $t->decimal('quantity', 12, 3)->default(1);
            $t->string('unit_name', 32)->nullable();
            $t->decimal('unit_net', 12, 4);
            $t->decimal('total_net', 12, 2);
            $t->decimal('tax_rate', 5, 2)->nullable();
            $t->char('currency', 3)->default('EUR');
            $t->timestamps();
            $t->unique(['voucher_id', 'position'], 'acc_voucher_line_uq');
            $t->index(['organization_id', 'article_ref'], 'acc_voucher_line_org_article_idx');
        });
        Schema::table('accounting_vouchers', function (Blueprint $t): void {
            $t->timestamp('lines_synced_at')->nullable()->after('synced_at');
        });
    }

    public function down(): void {
        Schema::table('accounting_vouchers', function (Blueprint $t): void {
            $t->dropColumn('lines_synced_at');
        });
        Schema::dropIfExists('accounting_voucher_lines');
    }
};
