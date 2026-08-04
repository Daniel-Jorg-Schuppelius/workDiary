<?php
/*
 * Created on   : Mon Aug 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_11_23_100000_create_text_corrections_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Schreibfehler-Wörterbuch je Organisation: deterministische Ersetzungen
 * (falsch => richtig), die beim Aufbau generierter Positionstexte
 * automatisch angewandt werden — die Quelldaten (TimeEntry) bleiben
 * unverändert. `wrong_normalized` trägt den case-insensitiven
 * Unique-Schlüssel selbst, damit das Verhalten auf MySQL und SQLite
 * (Test-Umgebung) identisch ist und nicht an einer Collation hängt.
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('text_corrections', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('wrong', 190);            // Anzeige-Schreibweise wie erfasst
            $table->string('wrong_normalized', 190); // toLower(normalizeWhitespace(wrong)), via Model-Hook
            $table->string('correct', 190);
            $table->boolean('active')->default(true);
            $table->string('origin', 10)->default('manual'); // manual|learned
            $table->unsignedInteger('usage_count')->default(0);
            $table->timestamp('last_used_at')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['organization_id', 'wrong_normalized'], 'txc_org_wrong_unique');
            $table->index(['organization_id', 'active'], 'txc_org_active_idx');
        });
    }

    public function down(): void {
        Schema::dropIfExists('text_corrections');
    }
};
