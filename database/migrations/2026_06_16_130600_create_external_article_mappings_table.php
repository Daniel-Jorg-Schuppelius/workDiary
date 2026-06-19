<?php
/*
 * Created on   : Tue Jun 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_06_16_130600_create_external_article_mappings_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Stabile Zuordnung interner Artikel/Varianten zu externen Provider-Artikeln
 * (Feature 048, MVP-060). JTL: Vater-/Kindreferenz (external_parent_id =
 * Vaterartikel, external_id = Kindartikel). Lexoffice: je Variante ein
 * eigenständiger Artikel. Namensgleichheit allein genügt nicht — Artikelnummer/
 * externe ID oder bestätigte Zuordnung erforderlich.
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('external_article_mappings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->nullable()->constrained('organizations')->nullOnDelete();
            $table->string('plugin_id', 64);
            $table->string('external_id', 128);
            $table->foreignId('article_id')->nullable()->constrained('articles')->cascadeOnDelete();
            $table->foreignId('article_variant_id')->nullable()->constrained('article_variants')->cascadeOnDelete();
            $table->string('external_parent_id', 128)->nullable();
            $table->string('external_number', 64)->nullable();
            $table->string('unit', 20)->nullable();
            $table->string('sync_status', 20)->default('pending');
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();

            $table->index('organization_id');
            $table->index('article_id');
            $table->index('article_variant_id');
            $table->unique(['organization_id', 'plugin_id', 'external_id'], 'ext_article_map_unique');
        });
    }

    public function down(): void {
        Schema::dropIfExists('external_article_mappings');
    }
};
