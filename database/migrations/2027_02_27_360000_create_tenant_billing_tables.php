<?php
/*
 * Created on   : Sun Sep 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_02_27_360000_create_tenant_billing_tables.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** MVP-956/957: monatliche Nutzungsstände je Mandant und Tarifwechsel-Anfragen. */
return new class extends Migration {
    public function up(): void {
        Schema::create('tenant_usage_snapshots', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations', indexName: 'tenant_usage_org_fk')->cascadeOnDelete();
            $table->date('period_on');
            $table->string('plan', 20);
            $table->json('addons')->nullable();
            $table->unsignedInteger('users');
            $table->unsignedInteger('active_users')->nullable();
            $table->unsignedBigInteger('storage_bytes');
            $table->unsignedSmallInteger('modules');
            $table->decimal('amount', 12, 2);
            $table->string('currency', 3);
            $table->timestamp('computed_at');
            $table->timestamps();
            $table->unique(['organization_id', 'period_on'], 'tenant_usage_org_period_unique');
        });

        Schema::create('tenant_plan_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations', indexName: 'tenant_plan_req_org_fk')->cascadeOnDelete();
            $table->string('requested_plan', 20);
            $table->json('requested_addons')->nullable();
            $table->string('note', 1000)->nullable();
            $table->string('status', 16);
            $table->foreignId('requester_user_id')->nullable()->constrained('users', indexName: 'tenant_plan_req_requester_fk')->nullOnDelete();
            $table->foreignId('decider_user_id')->nullable()->constrained('users', indexName: 'tenant_plan_req_decider_fk')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->string('decision_note', 500)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void {
        Schema::dropIfExists('tenant_plan_requests');
        Schema::dropIfExists('tenant_usage_snapshots');
    }
};
