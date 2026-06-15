<?php
/*
 * Created on   : Sat Jun 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_06_14_270000_create_room_requirements_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Raumbezogene fachliche Anforderungen je Gewerk (Feature 027).
 *
 * Eigene 1:n-Tabelle statt Room-Spalten: ein Raum kann mehrere fachliche
 * Anforderungen verschiedener Gewerke gleichzeitig tragen (Hygienestufe,
 * Sonderreinigung, Zugangsbeschränkung, IT-Inventar, technische Prüfung,
 * Betreiberpflicht), ohne den Raum doppelt anzulegen.
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('room_requirements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('room_id')->constrained('rooms')->cascadeOnDelete();
            $table->string('kind', 30);
            $table->string('level', 60)->nullable();
            $table->text('note')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['room_id', 'is_active'], 'room_req_idx_room');
            $table->index(['organization_id', 'kind'], 'room_req_idx_kind');
        });
    }

    public function down(): void {
        Schema::dropIfExists('room_requirements');
    }
};
