<?php
/*
 * Created on   : Sun Aug 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_12_06_103100_create_metal_quotations.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Metallnotierungen (Feature 107, MVP-564): org-weit gepflegte Tagespreise
 * je Rohstoff (DEL-Notiz für Kupfer, €/kg). Historisiert über `quoted_at`;
 * der jüngste Eintrag ist die aktuelle Notierung. Grundlage der
 * Kupferzuschlag-Berechnung im effektiven Einkaufspreis der Katalogartikel.
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('metal_quotations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('metal', 4); // Rohstoffmerker der DATANORM-Tabelle (CU, AL, AG, …)
            $table->decimal('price_per_kg', 12, 4);
            $table->string('currency', 3)->default('EUR');
            $table->date('quoted_at');
            $table->timestamps();

            $table->unique(['organization_id', 'metal', 'quoted_at'], 'mq_org_metal_date_unique');
            $table->index(['organization_id', 'metal', 'quoted_at'], 'mq_lookup_idx');
        });
    }

    public function down(): void {
        Schema::dropIfExists('metal_quotations');
    }
};
