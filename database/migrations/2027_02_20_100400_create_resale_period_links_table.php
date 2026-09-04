<?php
/*
 * Created on   : Fri Sep 04 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_02_20_100400_create_resale_period_links_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Feature 152 (MVP-761): Rechnungsbezug einer Abrechnungsperiode — welche
 * Belegposition (Spiegel oder lokale Rechnung) wie viele Lizenzmonate der
 * Periode deckt; Herkunft Vorschlag / bestätigt / manuell.
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('resale_period_links', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $t->foreignId('period_id')->constrained('resale_periods')->cascadeOnDelete();
            $t->foreignId('subscription_id')->constrained('resale_subscriptions')->cascadeOnDelete();
            $t->string('linkable_type', 120);
            $t->unsignedBigInteger('linkable_id');
            $t->string('voucher_number', 64)->nullable();
            $t->date('voucher_date')->nullable();
            $t->decimal('quantity', 10, 3)->default(0);      // gedeckte Lizenzen (anteilig)
            $t->decimal('months', 10, 2)->default(0);        // gedeckte Lizenzmonate
            $t->decimal('amount', 12, 2)->nullable();        // zugerechneter Nettobetrag
            $t->char('currency', 3)->default('EUR');
            $t->string('origin', 16)->default('proposed');   // proposed|confirmed|manual
            $t->string('note', 255)->nullable();
            $t->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('confirmed_at')->nullable();
            $t->timestamps();

            $t->unique(['period_id', 'linkable_type', 'linkable_id'], 'resale_links_period_linkable_uq');
            $t->index(['organization_id', 'linkable_type', 'linkable_id'], 'resale_links_org_linkable_idx');
            $t->index(['subscription_id', 'origin'], 'resale_links_sub_origin_idx');
        });

        Schema::table('resale_periods', function (Blueprint $t): void {
            $t->string('note', 255)->nullable()->after('waived_reason');
        });
    }

    public function down(): void {
        Schema::table('resale_periods', function (Blueprint $t): void {
            $t->dropColumn('note');
        });
        Schema::dropIfExists('resale_period_links');
    }
};
