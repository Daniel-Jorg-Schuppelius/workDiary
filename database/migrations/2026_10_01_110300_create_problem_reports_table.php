<?php
/*
 * Created on   : Wed Jul 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_10_01_110300_create_problem_reports_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Feature 041, P1 (MVP-053): In-App-Fehlermeldungen mit Seitenkontext,
 * optionalem redaktiertem Diagnoseauszug und stabiler Referenznummer.
 * Anhänge (Screenshots) laufen über die attachments-Polymorphie.
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('problem_reports', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')
                ->constrained('organizations', indexName: 'prr_org_fk')
                ->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()
                ->constrained('users', indexName: 'prr_user_fk')
                ->nullOnDelete();
            $table->string('reference_no', 30);
            $table->string('status', 20); // new/in_review/answered/closed
            $table->string('severity', 20); // low/normal/high/blocking
            $table->string('summary', 200);
            $table->text('description');
            $table->text('expected_behavior')->nullable();
            $table->text('actual_behavior')->nullable();
            $table->boolean('contact_ok')->default(false);
            $table->json('page_context'); // route/topic/version/build/request_id/…
            $table->json('diagnostic_excerpt')->nullable(); // nur nach Opt-in, redaktiert
            $table->foreignId('diagnostics_approved_by')->nullable()
                ->constrained('users', indexName: 'prr_diag_user_fk')
                ->nullOnDelete();
            $table->string('delivery_target', 20); // saas_inbox/mail/webhook/local_export
            $table->timestamp('delivered_at')->nullable();
            $table->string('delivery_error', 300)->nullable();
            $table->string('external_ref', 100)->nullable(); // z. B. Helpdesk-Ticket
            $table->timestamps();

            $table->unique(['organization_id', 'reference_no'], 'prr_org_ref_unique');
            $table->index(['organization_id', 'status'], 'prr_org_status_idx');
        });
    }

    public function down(): void {
        Schema::dropIfExists('problem_reports');
    }
};
