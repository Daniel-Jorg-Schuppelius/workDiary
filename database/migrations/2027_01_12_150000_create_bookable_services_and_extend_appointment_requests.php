<?php
/*
 * Created on   : Tue Aug 18 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_01_12_150000_create_bookable_services_and_extend_appointment_requests.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Online-Terminbuchung (Feature 087, MVP-666/667): buchbare Leistungsarten
 * und die Portal-Erweiterung der quellenagnostischen appointment_requests
 * (Feature 095 legte sie mit `bookable_service_id` als Platzhalter an —
 * hier bekommt er sein Ziel; bewusst OHNE FK, wie dort angelegt).
 */
return new class extends Migration {
    public function up(): void {
        if (! Schema::hasTable('bookable_services')) {
            Schema::create('bookable_services', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('title', 160);
            $table->string('description', 500)->nullable();
            $table->unsignedInteger('duration_minutes')->default(60);
            // Vorlauf: wie weit im Voraus frühestens gebucht werden kann.
            $table->unsignedInteger('lead_time_hours')->default(24);
            // Stornofrist: bis wann der Kunde selbst absagen darf.
            $table->unsignedInteger('cancel_hours')->default(24);
            $table->unsignedInteger('buffer_minutes')->default(15);
            $table->foreignId('site_id')->nullable()->constrained('sites')->nullOnDelete();
            $table->foreignId('required_qualification_id')->nullable()->constrained('qualifications')->nullOnDelete();
            $table->boolean('active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
        }

        // Idempotent (hasColumn-Guards): Ein teilgescheiterter sqlite-Lauf
        // ließ portal_user_id bereits zurück — sqlite kennt kein DDL-Rollback.
        Schema::table('appointment_requests', function (Blueprint $table): void {
            if (! Schema::hasColumn('appointment_requests', 'portal_user_id')) {
                $table->foreignId('portal_user_id')->nullable()->after('customer_id')->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('appointment_requests', 'decided_by')) {
                $table->foreignId('decided_by')->nullable()->after('assigned_user_id')->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('appointment_requests', 'decided_at')) {
                $table->timestamp('decided_at')->nullable()->after('decided_by');
            }
            if (! Schema::hasColumn('appointment_requests', 'decline_reason')) {
                $table->string('decline_reason', 500)->nullable()->after('decided_at');
            }
        });
    }

    public function down(): void {
        Schema::table('appointment_requests', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('portal_user_id');
            $table->dropConstrainedForeignId('decided_by');
            $table->dropColumn(['decided_at', 'decline_reason']);
        });
        Schema::dropIfExists('bookable_services');
    }
};
