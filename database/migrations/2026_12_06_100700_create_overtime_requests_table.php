<?php
/*
 * Created on   : Wed Aug 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_12_06_100700_create_overtime_requests_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * MVP-519 (Feature 103): Überstunden-Antrag — Mehrzeit über die Rahmenzeit
 * hinaus wird beantragt und genehmigt statt still verbucht. Die Genehmigung
 * dokumentiert die betriebliche Veranlassung (Audit) und quittiert einen
 * passenden Plausibilitäts-Befund („Rahmenzeit überschritten") automatisch.
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('overtime_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('requested_by_user_id')->constrained('users', indexName: 'ovr_req_by_fk')->cascadeOnDelete();
            $table->date('scope_date');
            $table->unsignedInteger('minutes');
            $table->text('reason');
            $table->string('status', 20)->default('submitted');
            $table->timestamp('decided_at')->nullable();
            $table->foreignId('decided_by_user_id')->nullable()->constrained('users', indexName: 'ovr_dec_by_fk')->nullOnDelete();
            $table->string('decision_note', 2000)->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'user_id', 'scope_date'], 'ovr_org_user_date_idx');
            $table->index(['organization_id', 'status'], 'ovr_org_status_idx');
        });
    }

    public function down(): void {
        Schema::dropIfExists('overtime_requests');
    }
};
