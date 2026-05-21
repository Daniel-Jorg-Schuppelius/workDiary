<?php
/*
 * Created on   : Thu May 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_05_21_140200_create_events_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->nullable()
                ->constrained('organizations')->cascadeOnDelete();

            // Inhalt
            $table->string('title', 200);
            $table->text('description')->nullable();
            $table->string('topic', 200)->nullable();

            // Klassifikation
            $table->string('event_type', 40)->default('training');
            $table->foreignId('category_id')->nullable()
                ->constrained('event_categories')->nullOnDelete();

            // Termin
            $table->dateTime('started_at');
            $table->dateTime('ended_at');
            $table->boolean('is_all_day')->default(false);
            $table->string('timezone', 64)->nullable();

            // Status & Sichtbarkeit
            $table->string('status', 20)->default('planned');
            $table->string('visibility', 20)->default('internal');

            // Verantwortlichkeiten
            $table->foreignId('responsible_user_id')->nullable()
                ->constrained('users')->nullOnDelete();
            $table->foreignId('customer_id')->nullable()
                ->constrained('customers')->nullOnDelete();
            $table->string('external_contact_note', 255)->nullable();

            // Teilnahme-Regeln
            $table->unsignedSmallInteger('max_participants')->nullable();
            $table->boolean('is_mandatory')->default(false);
            $table->unsignedSmallInteger('certificate_valid_months')->nullable();

            // Wiederholungen (RFC 5545 RRULE)
            //   series_id = self-FK auf den Master-Event einer Serie
            //   (Master selbst hat series_id = NULL und ggf. recurrence_rule).
            $table->foreignId('series_id')->nullable()
                ->constrained('events')->nullOnDelete();
            $table->text('recurrence_rule')->nullable();
            $table->dateTime('series_until')->nullable();

            // Reminder-Overrides pro Event (Minuten-Offsets). null = aus Kategorie/Config.
            $table->json('reminder_overrides')->nullable();

            // Abbruch
            $table->dateTime('cancelled_at')->nullable();
            $table->string('cancel_reason', 255)->nullable();

            // Audit
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['organization_id', 'started_at']);
            $table->index(['responsible_user_id', 'started_at']);
            $table->index(['event_type', 'started_at']);
            $table->index('series_id');
            $table->index(['status', 'started_at']);
        });
    }

    public function down(): void {
        Schema::dropIfExists('events');
    }
};
