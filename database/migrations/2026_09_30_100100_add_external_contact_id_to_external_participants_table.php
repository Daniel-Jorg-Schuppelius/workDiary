<?php
/*
 * Created on   : Mon Jul 07 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_09_30_100100_add_external_contact_id_to_external_participants_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Verknüpft eine Einladung optional mit einem wiederverwendbaren
 * {@see \App\Models\ExternalContact}-Stammdatensatz (Feature 033, Rang 30).
 * Nullable + nullOnDelete: die Einladung überlebt das Löschen des Stammdatensatzes
 * (Name/E-Mail bleiben denormalisiert als Nachweis erhalten).
 */
return new class extends Migration {
    public function up(): void {
        Schema::table('external_participants', function (Blueprint $table): void {
            $table->foreignId('external_contact_id')->nullable()->after('subject_id')
                ->constrained('external_contacts')->nullOnDelete();
        });
    }

    public function down(): void {
        Schema::table('external_participants', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('external_contact_id');
        });
    }
};
