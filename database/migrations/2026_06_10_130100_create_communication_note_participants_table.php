<?php
/*
 * Created on   : Wed Jun 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_06_10_130100_create_communication_note_participants_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('communication_note_participants', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('communication_note_id')->constrained('communication_notes')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            // Vorausschau auf Kunden-Kontakte (kein FK — Tabelle existiert noch nicht).
            $table->unsignedBigInteger('customer_contact_id')->nullable();
            $table->string('name', 120);
            $table->string('role', 40)->nullable();
            $table->string('party', 12);
            $table->timestamps();

            $table->unique(['communication_note_id', 'user_id', 'customer_contact_id', 'name'], 'comm_note_part_unique');
            $table->index(['communication_note_id'], 'comm_note_part_note_idx');
        });
    }

    public function down(): void {
        Schema::dropIfExists('communication_note_participants');
    }
};
