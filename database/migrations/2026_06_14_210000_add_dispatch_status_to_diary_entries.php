<?php
/*
 * Created on   : Sat Jun 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_06_14_210000_add_dispatch_status_to_diary_entries.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Feature 028 — Dispositionsstatus am Auftrag.
 *
 * Additive, nullable Spalte. Der effektive Status wird vom
 * DispatchStatusResolver bevorzugt aus dieser Spalte gelesen und sonst aus
 * den vorhandenen Planungsfeldern (planned_at/assigned_user_id/status/…)
 * abgeleitet. Die Spalte hält ausserdem den Override-Audit-Trail für die
 * bewusste Übersteuerung harter Konflikte bei der Terminbestätigung.
 *
 * Die WIP-Modellklasse DiaryEntry bleibt unangetastet — Lese-/Schreibzugriff
 * erfolgt ausschliesslich über den Query-Builder im Service-Layer.
 */
return new class extends Migration {
    public function up(): void {
        Schema::table('diary_entries', function (Blueprint $table): void {
            $table->string('dispatch_status', 20)->nullable()->after('planned_by_user_id');
            $table->timestamp('dispatch_confirmed_at')->nullable()->after('dispatch_status');
            $table->text('dispatch_override_reason')->nullable()->after('dispatch_confirmed_at');
            $table->foreignId('dispatch_override_by_user_id')->nullable()->after('dispatch_override_reason')
                ->constrained('users')->nullOnDelete();
            $table->index(['organization_id', 'dispatch_status'], 'diary_org_dispatch_idx');
        });
    }

    public function down(): void {
        Schema::table('diary_entries', function (Blueprint $table): void {
            $table->dropForeign(['dispatch_override_by_user_id']);
            $table->dropIndex('diary_org_dispatch_idx');
            $table->dropColumn([
                'dispatch_status',
                'dispatch_confirmed_at',
                'dispatch_override_reason',
                'dispatch_override_by_user_id',
            ]);
        });
    }
};
