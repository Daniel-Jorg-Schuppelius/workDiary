<?php
/*
 * Created on   : Wed Aug 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_02_19_104000_create_user_workspaces_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Eigene Arbeitsbereiche je Nutzer (Feature 082 Phase 2, MVP-731 — Vollscan G17).
 *
 * Die vordefinierten Fokus-Ansichten (`config/navigation_focus.php`) bleiben
 * das Produktangebot; hier kommt nur die persönliche Zusammenstellung dazu.
 * Deshalb eine eigene Tabelle statt eines weiteren Preference-Feldes: Ein
 * Arbeitsbereich hat Name, Symbol, Reihenfolge und eine Liste — das ist ein
 * Datensatz, kein Schalter.
 *
 * `items` sind Navigations-Schlüssel der {@see \App\Services\Navigation\NavigationRegistry}
 * (`section:`/`group:`/`item:`) — dieselbe Sprache wie `nav_hidden` und die
 * Fokus-Config. Was drin stehen darf, prüft der Server gegen NavGate; die
 * Spalte ist damit ein reiner Anzeigefilter und **nie** ein Rechte-Träger.
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('user_workspaces', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name', 60);
            $table->string('icon', 40)->nullable();
            $table->unsignedSmallInteger('sort')->default(0);
            $table->json('items');
            $table->timestamps();

            // Zwei gleichnamige Arbeitsbereiche wären im Umschalter nicht
            // unterscheidbar — der Name ist hier die Identität.
            $table->unique(['user_id', 'name'], 'user_workspace_user_name_unique');
            $table->index(['user_id', 'sort'], 'user_workspace_user_sort_idx');
        });
    }

    public function down(): void {
        Schema::dropIfExists('user_workspaces');
    }
};
