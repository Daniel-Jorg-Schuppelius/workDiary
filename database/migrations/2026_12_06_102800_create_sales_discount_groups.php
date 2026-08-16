<?php
/*
 * Created on   : Sat Aug 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_12_06_102800_create_sales_discount_groups.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Verkaufs-Rabattgruppen (Feature 107, W9 — Nutzer-Entscheidung 2026-08-16):
 * org-weite Standard-Konditionen für den DATANORM-Export mit Listenpreisen
 * (Kennzeichen 1 + RAB-Datei). Artikel referenzieren die Gruppe; Empfänger
 * rechnen Liste − Rabatt = Netto. Kundenindividuelle Abweichungen laufen
 * weiterhin über den B2B-DATPREIS (Nettopreise).
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('sales_discount_groups', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('code', 4);
            $table->string('kind', 16)->default('discount'); // discount | factor | surcharge
            $table->decimal('value', 10, 4);
            $table->string('label')->nullable();
            $table->timestamps();

            $table->unique(['organization_id', 'code'], 'sdg_org_code_unique');
        });

        Schema::table('articles', function (Blueprint $table): void {
            $table->foreignId('sales_discount_group_id')->nullable()->after('subcategory')
                ->constrained('sales_discount_groups', indexName: 'articles_sdg_fk')->nullOnDelete();
        });
    }

    public function down(): void {
        Schema::table('articles', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('sales_discount_group_id');
        });
        Schema::dropIfExists('sales_discount_groups');
    }
};
