<?php
/*
 * Created on   : Sun Sep 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_02_27_320000_create_rental_rate_rules_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** MVP-950: Mietpreisregeln (Saison, Wochentage, Auslastung) an der Preisliste. */
return new class extends Migration {
    public function up(): void {
        Schema::create('rental_rate_rules', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations', indexName: 'rental_rules_org_fk')->cascadeOnDelete();
            $table->foreignId('rental_rate_card_id')->constrained('rental_rate_cards', indexName: 'rental_rules_card_fk')->cascadeOnDelete();
            $table->string('kind', 16);
            $table->string('label', 200);
            $table->date('valid_from')->nullable();
            $table->date('valid_until')->nullable();
            $table->json('weekdays')->nullable();
            $table->unsignedTinyInteger('utilization_min_percent')->nullable();
            $table->decimal('adjust_percent', 6, 2);
            $table->timestamps();
        });
    }

    public function down(): void {
        Schema::dropIfExists('rental_rate_rules');
    }
};
