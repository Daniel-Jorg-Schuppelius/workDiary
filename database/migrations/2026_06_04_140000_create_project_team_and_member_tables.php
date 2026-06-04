<?php
/*
 * Created on   : Thu Jun 04 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_06_04_140000_create_project_team_and_member_tables.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Zuordnung von Aufträgen (Projekten) zu Arbeits-Teams (n:m) sowie optionale
 * Einzelmitglieder je Projekt. Die Vereinigung aus Team-Mitgliedern und
 * Einzelmitgliedern bildet den Kreis der für Aufgaben zuweisbaren Personen.
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('project_team', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['project_id', 'team_id']);
        });

        Schema::create('project_user', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['project_id', 'user_id']);
        });
    }

    public function down(): void {
        Schema::dropIfExists('project_user');
        Schema::dropIfExists('project_team');
    }
};
