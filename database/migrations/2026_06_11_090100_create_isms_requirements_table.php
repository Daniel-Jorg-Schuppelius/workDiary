<?php
/*
 * Created on   : Thu Jun 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_06_11_090100_create_isms_requirements_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Normanforderungen (Feature 046, gemeinsamer Kern): versionierte Referenzen
 * (Norm + Ausgabe + Referenznummer + eigener Kurztitel — KEINE Normtexte,
 * Urheberrecht!). Normkataloge (config/isms-norms/, NormProfileRegistry)
 * werden beim Import als Requirements mit norm/edition des Profils und
 * source 'catalog' angelegt; eigene Anforderungen tragen source 'custom'.
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('isms_requirements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            // Normbezeichnung, z. B. "ISO/IEC 27001" — oder "Eigene" für freie Anforderungen.
            $table->string('norm', 64);
            // Ausgabe/Revision, z. B. "2022" — Normrevisionen laufen parallel (Feature 046).
            $table->string('edition', 16);
            // Referenznummer, z. B. "A.5.1" (Annex A) oder freie Kennung.
            $table->string('ref_no', 24);
            $table->string('title', 180);
            $table->string('source', 16)->default('custom');
            $table->timestamps();
            $table->softDeletes();

            // Kurzer expliziter Name (MySQL-64-Zeichen-Limit).
            $table->unique(['organization_id', 'norm', 'edition', 'ref_no'], 'isms_req_org_ref_uq');
            $table->index(['organization_id', 'source'], 'isms_req_org_source_idx');
        });
    }

    public function down(): void {
        Schema::dropIfExists('isms_requirements');
    }
};
