<?php
/*
 * Created on   : Tue Aug 18 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_01_12_120000_create_access_media_tables.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Zutritts- und Transponderverwaltung, Stufe 1 (Feature 092, MVP-657/658):
 * verwaltete Medienbestände mit Verbleib — KEINE Live-Anlagensteuerung.
 *
 * Die Mediennummer wird nur **gehasht** gespeichert (Muster user_badges);
 * sichtbar bleibt ein Anzeige-Suffix. Der Inhaber ist Nutzer ODER externe
 * Person (Q1-Prüfpunkt: access_media darf nicht hart an users hängen —
 * Reinigungsdienste haben kein Mitarbeiterkonto).
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('access_media', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('type', 24)->default('transponder');
            $table->string('number_hash', 128);
            $table->string('number_suffix', 8);
            $table->string('label', 120)->nullable();
            $table->foreignId('site_id')->nullable()->constrained('sites')->nullOnDelete();
            // Freitext: welche Anlage/welches System das Medium kennt - die
            // Sperr-Aufgabe nennt sie beim Verlust.
            $table->string('system_name', 120)->nullable();
            $table->string('status', 24)->default('in_stock');
            // Aktueller Inhaber (denormalisiert für Listen; Historie in handovers).
            $table->foreignId('holder_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('holder_name', 160)->nullable();
            $table->string('holder_company', 160)->nullable();
            // Verlust/Sperrung: die verpflichtende Sperr-Aufgabe + ihr Nachweis.
            $table->foreignId('block_task_id')->nullable()->constrained('tasks')->nullOnDelete();
            $table->timestamp('blocked_at')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['organization_id', 'number_hash'], 'access_media_org_hash_uq');
            $table->index(['organization_id', 'status'], 'access_media_org_status_idx');
        });

        Schema::create('access_medium_handovers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('access_medium_id')->constrained('access_media')->cascadeOnDelete();
            $table->string('direction', 12); // issue | return
            $table->foreignId('holder_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('holder_name', 160)->nullable();
            $table->string('holder_company', 160)->nullable();
            $table->timestamp('occurred_at');
            $table->timestamp('expected_return_at')->nullable();
            $table->string('condition', 500)->nullable();
            $table->foreignId('performed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['access_medium_id', 'occurred_at'], 'amh_medium_time_idx');
        });
    }

    public function down(): void {
        Schema::dropIfExists('access_medium_handovers');
        Schema::dropIfExists('access_media');
    }
};
