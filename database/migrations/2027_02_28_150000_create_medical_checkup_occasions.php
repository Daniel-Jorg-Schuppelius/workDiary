<?php
/*
 * Created on   : Mon Sep 28 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_02_28_150000_create_medical_checkup_occasions.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** MVP-986: Katalog der Vorsorgeanlässe mit Intervall; die Vorsorge verweist optional darauf. */
return new class extends Migration {
    public function up(): void {
        Schema::create('medical_checkup_occasions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations', indexName: 'mc_occasion_org_fk')->cascadeOnDelete();
            $table->string('label', 180);
            $table->string('kind', 20);
            $table->unsignedSmallInteger('interval_months')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users', indexName: 'mc_occasion_creator_fk')->nullOnDelete();
            $table->timestamps();
            $table->unique(['organization_id', 'label'], 'mc_occasion_org_label_unique');
        });

        Schema::table('medical_checkups', function (Blueprint $table): void {
            $table->foreignId('medical_checkup_occasion_id')->nullable()->after('kind')
                ->constrained('medical_checkup_occasions', indexName: 'mc_checkup_occasion_fk')->nullOnDelete();
        });
    }

    public function down(): void {
        Schema::table('medical_checkups', function (Blueprint $table): void {
            $table->dropForeign('mc_checkup_occasion_fk');
            $table->dropColumn('medical_checkup_occasion_id');
        });
        Schema::dropIfExists('medical_checkup_occasions');
    }
};
