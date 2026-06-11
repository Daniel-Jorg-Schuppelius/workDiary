<?php
/*
 * Created on   : Wed Jun 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_06_10_150100_create_notification_rules_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Benachrichtigungsregeln pro Organisation und Ereignistyp (MVP-018).
 * Fehlt eine Zeile, greift der Code-Default aus NotificationEvent
 * (enabled, inApp+mail an Betroffene) — siehe NotificationRule::resolveFor().
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('notification_rules', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('event', 64);
            $table->boolean('enabled')->default(true);
            /** Kanäle: Teilmenge aus inApp|mail|push (NotificationChannel). */
            $table->json('channels');
            /** Empfänger-Strategie: betroffene Person und/oder Rollen und/oder feste User. */
            $table->boolean('notify_affected')->default(true);
            $table->json('recipient_roles')->nullable();
            $table->json('recipient_user_ids')->nullable();
            /** Eskalation light: nach X Stunden unerledigt zusätzlich an Rolle. */
            $table->boolean('escalation_enabled')->default(false);
            $table->unsignedSmallInteger('escalate_after_hours')->nullable();
            $table->string('escalation_role', 64)->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['organization_id', 'event'], 'notif_rules_org_event_uq');
        });
    }

    public function down(): void {
        Schema::dropIfExists('notification_rules');
    }
};
