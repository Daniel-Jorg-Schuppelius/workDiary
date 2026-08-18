<?php
/*
 * Created on   : Tue Aug 18 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_01_10_130000_create_catalog_registry_tables.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Katalogstamm (Feature 109, MVP-637).
 *
 * Die Kataloge, gegen die zugeordnet wird, liegen bisher nur als Kopfeintrag im
 * jeweiligen Leistungsverzeichnis (`boq_catalogs`) — was dort steht, hat die
 * Vergabestelle mitgeschickt. Für Vorschlagsregeln, Auswertungen und den
 * Ausgabenwechsel braucht es dagegen einen **Stamm**: die Kostengruppen selbst,
 * mit Nummer, Kurzbezeichnung und Ebene.
 *
 * Zwei Festlegungen prägen die Struktur:
 *
 * - **Katalog und Ausgabe gehören zum Schlüssel** (D3). „310" bedeutet in
 *   DIN 276-1:2008-12 etwas anderes als in DIN 276:2018-12; deshalb trägt jeder
 *   Stamm seine Ausgabe, und der GAEB-Katalogtyp (`cost group DIN 276 2018-12`)
 *   steht daneben, damit ein Import ihn ohne Raten zuordnen kann.
 * - **Der Stamm ist organisationsübergreifend, die freien Kataloge sind es
 *   nicht** (D7). DIN 276:2018 ist für alle Mandanten dieselbe Liste
 *   (`organization_id` = NULL); Gebäude, Kostenträger und Kostenstellen gehören
 *   der Organisation.
 *
 * Ausgeliefert werden ausschließlich **Nummern und Kurzbezeichnungen** (D6) —
 * kein Normtext, keine lizenzpflichtigen Katalogtexte.
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('catalog_registries', function (Blueprint $table): void {
            $table->id();
            // NULL = ausgelieferter Stamm für alle Mandanten (D7).
            $table->foreignId('organization_id')->nullable()->constrained()->cascadeOnDelete();

            $table->string('key', 60);
            $table->string('kind', 40);
            $table->string('name', 160);
            $table->string('edition', 40)->nullable();
            // Der GAEB-Katalogtyp, unter dem dieser Stamm in Dateien auftritt —
            // damit ein Import ihn ohne Raten zuordnen kann.
            $table->string('gaeb_type', 80)->nullable();
            $table->unsignedTinyInteger('levels')->default(1);
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->unique(['organization_id', 'key'], 'catreg_org_key_uq');
            $table->index(['kind', 'active'], 'catreg_kind_active_idx');
            $table->index('gaeb_type', 'catreg_gaeb_type_idx');
        });

        Schema::create('catalog_entries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('catalog_registry_id')->constrained()->cascadeOnDelete();

            $table->string('code', 40);
            $table->string('label', 300);
            // Kurzbezeichnungen der übrigen Sprachen; die deutsche steht in
            // `label`, weil sie die amtliche ist.
            $table->json('labels')->nullable();
            $table->unsignedTinyInteger('level')->default(1);
            $table->string('parent_code', 40)->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();

            $table->unique(['catalog_registry_id', 'code'], 'catent_reg_code_uq');
            $table->index(['catalog_registry_id', 'parent_code'], 'catent_reg_parent_idx');
        });

        // Zuordnungstabelle für den Ausgabenwechsel (MVP-641): Sie wird als
        // Vorschlag angewandt, nie automatisch - ein Wechsel der Norm ist eine
        // fachliche Entscheidung, keine Datenmigration.
        Schema::create('catalog_code_mappings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('from_registry_id')->constrained('catalog_registries', indexName: 'catmap_from_fk')->cascadeOnDelete();
            $table->foreignId('to_registry_id')->constrained('catalog_registries', indexName: 'catmap_to_fk')->cascadeOnDelete();
            $table->string('from_code', 40);
            $table->string('to_code', 40);
            $table->string('note', 300)->nullable();
            $table->timestamps();

            $table->unique(['from_registry_id', 'to_registry_id', 'from_code'], 'catmap_from_to_code_uq');
        });
    }

    public function down(): void {
        Schema::dropIfExists('catalog_code_mappings');
        Schema::dropIfExists('catalog_entries');
        Schema::dropIfExists('catalog_registries');
    }
};
