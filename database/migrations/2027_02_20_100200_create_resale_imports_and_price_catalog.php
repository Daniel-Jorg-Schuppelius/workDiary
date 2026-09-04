<?php
/*
 * Created on   : Fri Sep 04 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_02_20_100200_create_resale_imports_and_price_catalog.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Feature 152 (MVP-759): Import-Läufe der Anbieter-Exporte, der Einkaufs-
 * Preiskatalog je Anbieter und die Herkunftsspalten am Abo (Firma laut
 * Anbieter, Lexoffice-Artikel als Produkt- und Preisquelle, Import-Lauf,
 * zuletzt gesehen).
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('resale_imports', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $t->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $t->string('provider', 32);                      // SubscriptionProvider
            $t->string('kind', 16);                          // purchases|contracts|pricelist
            $t->string('file_name', 190);
            $t->string('file_path', 255)->nullable();
            $t->string('status', 16)->default('done');       // done|failed
            $t->unsignedInteger('rows_total')->default(0);
            $t->unsignedInteger('rows_created')->default(0);
            $t->unsignedInteger('rows_updated')->default(0);
            $t->unsignedInteger('rows_unchanged')->default(0);
            $t->unsignedInteger('rows_unassigned')->default(0);
            $t->json('issues')->nullable();
            $t->text('error')->nullable();
            $t->timestamps();

            $t->index(['organization_id', 'created_at'], 'resale_imports_org_created_idx');
        });

        Schema::create('resale_price_catalog', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $t->foreignId('import_id')->nullable()->constrained('resale_imports')->nullOnDelete();
            $t->string('provider', 32);
            $t->string('product', 190);                      // Produktname laut Anbieter
            $t->unsignedSmallInteger('term_months')->default(12);
            $t->string('interval', 8)->default('yearly');
            $t->date('valid_from');
            $t->date('valid_to')->nullable();
            $t->decimal('purchase_unit_price', 12, 4);       // Einkauf je Stück und Intervall
            $t->decimal('list_unit_price', 12, 4)->nullable(); // UVP je Stück und Intervall
            $t->char('currency', 3)->default('EUR');
            $t->timestamps();

            $t->unique(['organization_id', 'provider', 'product', 'term_months', 'interval', 'valid_from'], 'resale_prices_uq');
        });

        Schema::table('resale_subscriptions', function (Blueprint $t): void {
            $t->string('company_name', 190)->nullable()->after('label');   // Firma laut Anbieter-Export
            $t->foreignId('lexoffice_article_id')->nullable()->after('article_id')->constrained('lexoffice_articles')->nullOnDelete();
            $t->foreignId('import_id')->nullable()->after('raw_hash')->constrained('resale_imports')->nullOnDelete();
            $t->timestamp('last_seen_at')->nullable()->after('import_id');
        });
    }

    public function down(): void {
        Schema::table('resale_subscriptions', function (Blueprint $t): void {
            $t->dropConstrainedForeignId('lexoffice_article_id');
            $t->dropConstrainedForeignId('import_id');
            $t->dropColumn(['company_name', 'last_seen_at']);
        });
        Schema::dropIfExists('resale_price_catalog');
        Schema::dropIfExists('resale_imports');
    }
};
