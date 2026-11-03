<?php
/*
 * Created on   : Tue Nov 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_04_02_120000_create_project_time_targets_tables.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Projekt-Sollzeiten je Person (Feature 194, MVP-1467): Soll, Tagesbefund, Abrechnungsschalter. */
return new class extends Migration {
    public function up(): void {
        Schema::create('project_time_targets', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $t->foreignId('project_id')->constrained()->cascadeOnDelete();
            // null = Projektvorgabe für alle Mitglieder.
            $t->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $t->foreignId('site_id')->nullable()->constrained('sites')->nullOnDelete();
            // ISO-Wochentag 1–7; null = festes Datum in specific_date.
            $t->unsignedTinyInteger('weekday')->nullable();
            $t->date('specific_date')->nullable();
            $t->unsignedSmallInteger('target_minutes');
            $t->date('valid_from')->nullable();
            $t->date('valid_until')->nullable();
            $t->text('note')->nullable();
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
            $t->index(['project_id', 'user_id', 'weekday'], 'ptt_project_user_weekday_idx');
            $t->index(['project_id', 'user_id', 'specific_date'], 'ptt_project_user_date_idx');
        });

        Schema::create('project_time_shortfalls', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $t->foreignId('project_id')->constrained()->cascadeOnDelete();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->date('date');
            $t->unsignedSmallInteger('target_minutes');
            $t->unsignedInteger('booked_minutes');
            $t->unsignedSmallInteger('shortfall_minutes');
            $t->unsignedSmallInteger('tolerance_minutes')->default(0);
            $t->string('status', 16)->default('open');
            $t->foreignId('acknowledged_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('acknowledged_at')->nullable();
            $t->timestamp('resolved_at')->nullable();
            $t->timestamp('notified_at')->nullable();
            $t->text('note')->nullable();
            // Abrechnung nach Soll (MVP-1470): berechnete Auffüllung und ihre Position.
            $t->unsignedSmallInteger('billed_minutes')->nullable();
            $t->timestamp('billed_at')->nullable();
            $t->foreignId('billing_transfer_position_id')->nullable()->constrained('billing_transfer_positions')->nullOnDelete();
            $t->timestamps();
            $t->unique(['project_id', 'user_id', 'date'], 'pts_project_user_date_uq');
            $t->index(['organization_id', 'status', 'date'], 'pts_org_status_date_idx');
        });

        Schema::table('projects', function (Blueprint $t): void {
            $t->boolean('bills_target_time')->default(false)->after('billable');
        });
    }

    public function down(): void {
        Schema::table('projects', function (Blueprint $t): void {
            $t->dropColumn('bills_target_time');
        });
        Schema::dropIfExists('project_time_shortfalls');
        Schema::dropIfExists('project_time_targets');
    }
};
