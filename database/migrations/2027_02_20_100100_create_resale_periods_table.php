<?php
/*
 * Created on   : Fri Sep 04 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_02_20_100100_create_resale_periods_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Feature 152 (MVP-758): erwartete Abrechnungsperioden je Abo — eindeutig je
 * (Abo, Beginn), damit Neuplanung idempotent bleibt und Entscheidungen
 * (berechnet, verzichtet, strittig) an der Periode hängen.
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('resale_periods', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $t->foreignId('subscription_id')->constrained('resale_subscriptions')->cascadeOnDelete();
            $t->date('starts_on');
            $t->date('ends_on');
            $t->unsignedInteger('quantity')->default(1);
            $t->decimal('expected_purchase', 12, 2)->nullable();
            $t->decimal('expected_sale', 12, 2)->nullable();
            $t->char('currency', 3)->default('EUR');
            $t->string('status', 16)->default('open');       // open|billed|partial|waived|disputed
            $t->string('waived_reason', 255)->nullable();
            $t->foreignId('decided_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('decided_at')->nullable();
            $t->timestamps();

            $t->unique(['subscription_id', 'starts_on'], 'resale_periods_sub_start_uq');
            $t->index(['organization_id', 'status', 'starts_on'], 'resale_periods_org_status_start_idx');
        });
    }

    public function down(): void {
        Schema::dropIfExists('resale_periods');
    }
};
