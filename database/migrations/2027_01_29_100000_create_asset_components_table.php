<?php
/*
 * Created on   : Thu Aug 20 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_01_29_100000_create_asset_components_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Anlagen-Stückliste (Feature 118, MVP-607).
 *
 * Beschreibt, **was in diesem einen Gerät verbaut ist** — nicht, wie ein
 * Artikel hergestellt wird. Die Fertigungs-Stückliste (Feature 048) hängt am
 * Artikel (Typ), diese hier am Asset (Exemplar): verschiedene Fragen,
 * verschiedene Lebensdauern, verschiedene Verantwortliche. Eine gemeinsame
 * Struktur würde beide verbiegen.
 *
 * Ein ersetztes Teil wird NICHT überschrieben, sondern mit `removed_on`
 * stehen gelassen: „Was war vorher drin" ist bei wiederkehrenden Defekten die
 * entscheidende Frage.
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('asset_components', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('asset_id')->constrained()->cascadeOnDelete();
            // Artikel aus dem Stamm ODER Freitext für Fremdteile — nicht jedes
            // verbaute Teil ist im eigenen Artikelstamm.
            $table->foreignId('article_id')->nullable()->constrained()->nullOnDelete();
            $table->string('label', 191)->nullable();
            $table->decimal('quantity', 12, 3)->default('1.000');
            $table->string('unit', 32)->nullable();
            $table->string('position', 120)->nullable();
            $table->string('serial_no', 120)->nullable();
            $table->date('installed_on')->nullable();
            $table->date('removed_on')->nullable();
            $table->unsignedSmallInteger('replace_interval_months')->nullable();
            // installed | removed | replaced
            $table->string('status', 16)->default('installed');
            $table->foreignId('replaced_by_id')->nullable()->constrained('asset_components')->nullOnDelete();
            $table->string('note', 500)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['organization_id', 'asset_id', 'status'], 'asset_comp_org_asset_status_idx');
        });
    }

    public function down(): void {
        Schema::dropIfExists('asset_components');
    }
};
