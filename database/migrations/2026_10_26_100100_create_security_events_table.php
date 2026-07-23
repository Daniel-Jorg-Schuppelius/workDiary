<?php
/*
 * Created on   : Tue Jul 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_10_26_100100_create_security_events_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Persistierte Sicherheitsereignisse (Feature 096, MVP-445) für Schwellwert-
 * Alarme + Admin-Dashboard. Datensparsam: kurze Retention (config/security),
 * Hinweisgeber-Ereignisse werden nie persistiert (SecurityEventType::persist).
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('security_events', function (Blueprint $table): void {
            $table->id();
            $table->string('event', 40);
            $table->string('ip', 45)->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('organization_id')->nullable()->constrained('organizations')->nullOnDelete();
            $table->json('meta')->nullable();
            $table->dateTime('occurred_at');
            $table->timestamps();

            $table->index(['event', 'occurred_at'], 'se_event_time_idx');
            $table->index(['ip', 'occurred_at'], 'se_ip_time_idx');
            $table->index('occurred_at', 'se_time_idx');
        });
    }

    public function down(): void {
        Schema::dropIfExists('security_events');
    }
};
