<?php
/*
 * Created on   : Tue Sep 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_02_23_100400_create_club_attendance_tables.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bestätigte Vereinsanwesenheit (Feature 159, MVP-844): eine Liste je
 * Termin mit Sperrzähler gegen paralleles Überschreiben, ein Nachweis je
 * Mitglied in ganzen Minuten, bestätigte Versionen als Schnappschuss und
 * begründete Korrekturen. Getrennt von Anmeldung (club_event_participations)
 * und Arbeitszeit (attendances).
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('club_attendance_sheets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('event_id')->constrained('events')->cascadeOnDelete();
            $table->string('status', 16)->default('open'); // open|confirmed
            // Tatsächlich durchgeführte Dauer ohne Pausen; Obergrenze je Nachweis.
            $table->unsignedSmallInteger('conducted_minutes')->nullable();
            // Sperrzähler: jede Änderung erhöht ihn, Formulare senden den gelesenen Stand mit.
            $table->unsignedInteger('version')->default(0);
            $table->boolean('changed_since_confirmation')->default(true);
            $table->timestamp('confirmed_at')->nullable();
            $table->foreignId('confirmed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('first_confirmed_at')->nullable();
            $table->string('note', 255)->nullable();
            $table->timestamps();

            $table->unique('event_id', 'club_att_sheet_event_uq');
            $table->index(['organization_id', 'status'], 'club_att_sheet_org_status_idx');
        });

        Schema::create('club_attendance_records', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('club_attendance_sheet_id')->constrained('club_attendance_sheets')->cascadeOnDelete();
            $table->foreignId('event_id')->constrained('events')->cascadeOnDelete();
            $table->foreignId('club_member_id')->constrained('club_members')->cascadeOnDelete();
            $table->string('status', 16); // present|partial|excused|absent
            $table->unsignedSmallInteger('minutes')->nullable();
            $table->timestamp('arrived_at')->nullable();
            $table->timestamp('left_at')->nullable();
            $table->boolean('spontaneous')->default(false);
            // Überschneidung mit einem anderen bestätigten Nachweis desselben Mitglieds — bis zur Klärung keine Gutschrift.
            $table->foreignId('overlap_event_id')->nullable()->constrained('events')->nullOnDelete();
            $table->timestamp('overlap_cleared_at')->nullable();
            $table->foreignId('overlap_cleared_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('recorded_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('recorded_at')->nullable();
            $table->timestamps();

            $table->unique(['club_attendance_sheet_id', 'club_member_id'], 'club_att_rec_sheet_member_uq');
            $table->index(['club_member_id', 'status'], 'club_att_rec_member_status_idx');
            $table->index(['organization_id', 'event_id'], 'club_att_rec_org_event_idx');
        });

        Schema::create('club_attendance_revisions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('club_attendance_record_id')->constrained('club_attendance_records')->cascadeOnDelete();
            $table->unsignedInteger('sheet_version');
            $table->string('previous_status', 16)->nullable();
            $table->unsignedSmallInteger('previous_minutes')->nullable();
            $table->string('status', 16);
            $table->unsignedSmallInteger('minutes')->nullable();
            $table->string('reason', 255);
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['club_attendance_record_id', 'created_at'], 'club_att_rev_record_created_idx');
        });

        Schema::create('club_attendance_confirmations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('club_attendance_sheet_id')->constrained('club_attendance_sheets')->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->unsignedSmallInteger('conducted_minutes')->nullable();
            $table->json('snapshot');
            $table->foreignId('confirmed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('confirmed_at');
            $table->timestamps();

            $table->unique(['club_attendance_sheet_id', 'version'], 'club_att_conf_sheet_version_uq');
        });
    }

    public function down(): void {
        Schema::dropIfExists('club_attendance_confirmations');
        Schema::dropIfExists('club_attendance_revisions');
        Schema::dropIfExists('club_attendance_records');
        Schema::dropIfExists('club_attendance_sheets');
    }
};
