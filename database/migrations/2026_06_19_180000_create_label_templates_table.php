<?php
/*
 * Created on   : Fri Jun 19 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_06_19_180000_create_label_templates_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Etikettenvorlagen (Feature 047/048, E5): benannte Layouts mit Papiergröße,
 * Ausrichtung, QR-Schalter und Auswahl der dargestellten Felder. Eine Vorlage je
 * Organisation kann als Standard markiert sein.
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('label_templates', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('name', 120);
            $table->string('paper_size', 8)->default('a7');
            $table->string('orientation', 10)->default('landscape');
            $table->boolean('with_qr')->default(true);
            $table->json('fields'); // list<string>: title/subtitle/code/code_type/lines
            $table->boolean('is_default')->default(false);
            $table->timestamps();

            $table->unique(['organization_id', 'name'], 'label_templates_org_name_uq');
        });
    }

    public function down(): void {
        Schema::dropIfExists('label_templates');
    }
};
