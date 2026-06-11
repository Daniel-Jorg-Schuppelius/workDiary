<?php
/*
 * Created on   : Wed Jun 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_06_10_150200_create_notification_dispatch_log_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Dedup-/Nachweis-Log für den Fristen-Scanner (MVP-018): pro Ereignis,
 * Subjekt und Stufe (initial|escalation) genau ein Versand. Der Unique-Key
 * verhindert Doppel-Versand auch bei parallelen Scanner-Läufen; gleichzeitig
 * können Admins nachvollziehen, dass eine kritische Benachrichtigung erzeugt
 * wurde (Akzeptanzkriterium „auditierbare Zustellung").
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('notification_dispatch_log', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('event', 64);
            $table->string('subject_type');
            $table->unsignedBigInteger('subject_id');
            $table->string('stage', 16)->default('initial');
            $table->unsignedSmallInteger('recipient_count')->default(0);
            $table->timestamps();

            $table->unique(
                ['organization_id', 'event', 'subject_type', 'subject_id', 'stage'],
                'notif_dispatch_uq'
            );
            $table->index(['event', 'stage'], 'notif_dispatch_event_idx');
        });
    }

    public function down(): void {
        Schema::dropIfExists('notification_dispatch_log');
    }
};
