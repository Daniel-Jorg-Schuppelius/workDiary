<?php
/*
 * Created on   : Mon Jul 06 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_09_17_100000_create_attendance_terminals_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Hardware-Stempelterminal je Organisation (Feature 061, MVP-130): fest
 * montiertes Terminal (RFID/NFC/PIN) am Werkstatt-/Halleneingang, optional einem
 * Standort ({@see \App\Models\Site}) zugeordnet. Der Ingest-Endpunkt ist über
 * einen Gerätetoken im Pfad autorisiert — gespeichert wird nur der SHA-256-Hash
 * (Klartext einmalig, Muster wie `location_device_tokens`). `last_seen_at` trägt
 * den Gesundheitsstatus (Terminalausfall sichtbar).
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('attendance_terminals', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations', indexName: 'attterm_org_fk')->cascadeOnDelete();
            $table->foreignId('site_id')->nullable()->constrained('sites', indexName: 'attterm_site_fk')->nullOnDelete();
            $table->string('name');
            $table->string('token_hash', 64)->unique('attterm_token_unique');
            $table->boolean('active')->default(true);
            $table->timestamp('last_seen_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users', indexName: 'attterm_creator_fk')->nullOnDelete();
            $table->timestamps();

            $table->index('organization_id', 'attterm_org_idx');
        });
    }

    public function down(): void {
        Schema::dropIfExists('attendance_terminals');
    }
};
