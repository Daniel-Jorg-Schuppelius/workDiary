<?php
/*
 * Created on   : Sun Jul 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_10_12_100000_add_escalation_ladder_to_notification_rules.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Eskalationsleiter Stufe 2/3 (MVP-331, Bauturbo A11): optionale weitere
 * Eskalationsstufen je Benachrichtigungsregel — jede Stufe mit eigener Frist
 * (Stunden nach dem Versand der VORHERIGEN Stufe) und eigener Empfängergruppe
 * (Rollen und/oder feste User). Rein additiv: bestehende einstufige Regeln
 * (escalation_enabled/escalate_after_hours/escalation_role) bleiben unverändert
 * wirksam; ohne gesetzte Stufen-Spalten ändert sich nichts.
 */
return new class extends Migration {
    public function up(): void {
        Schema::table('notification_rules', function (Blueprint $table): void {
            /** Stufe 2: Frist in Stunden nach dem Stufe-1-Versand. */
            $table->unsignedSmallInteger('escalation2_after_hours')->nullable()->after('escalation_role');
            $table->json('escalation2_roles')->nullable()->after('escalation2_after_hours');
            $table->json('escalation2_user_ids')->nullable()->after('escalation2_roles');
            /** Stufe 3: Frist in Stunden nach dem Stufe-2-Versand. */
            $table->unsignedSmallInteger('escalation3_after_hours')->nullable()->after('escalation2_user_ids');
            $table->json('escalation3_roles')->nullable()->after('escalation3_after_hours');
            $table->json('escalation3_user_ids')->nullable()->after('escalation3_roles');
        });
    }

    public function down(): void {
        Schema::table('notification_rules', function (Blueprint $table): void {
            $table->dropColumn([
                'escalation2_after_hours',
                'escalation2_roles',
                'escalation2_user_ids',
                'escalation3_after_hours',
                'escalation3_roles',
                'escalation3_user_ids',
            ]);
        });
    }
};
