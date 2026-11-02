<?php
/*
 * Created on   : Mon Nov 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_04_02_110000_create_accounting_articles_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Artikelspiegel der Buchhaltungsprogramme ohne eigenen Artikel-Sync
 * (easybill, sevDesk, Invoice Ninja, Akaunting, InvoicePlane; Feature 185,
 * Behebung gap-09): eine Tabelle für alle, Katalogschlüssel `<präfix>:<id>`
 * je Programm. `number` trägt die Kennung, die Belegpositionen führen, wenn
 * sie nicht die Fremd-ID ist (easybill-Artikelnummer, Invoice-Ninja-`product_key`).
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('accounting_articles', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $t->string('plugin_id', 32);
            $t->string('external_id', 128);
            $t->string('number', 128)->nullable();
            $t->string('name', 255);
            $t->text('description')->nullable();
            $t->string('unit_name', 32)->nullable();
            $t->decimal('net_unit_price', 12, 4)->nullable();
            $t->char('currency', 3)->default('EUR');
            $t->decimal('vat_rate', 5, 2)->nullable();
            $t->string('kind', 16)->default('service');
            $t->timestamp('archived_at')->nullable();
            $t->timestamp('synced_at')->nullable();
            $t->timestamps();
            $t->unique(['organization_id', 'plugin_id', 'external_id'], 'acc_article_org_plugin_ext_uq');
            $t->index(['organization_id', 'plugin_id', 'number'], 'acc_article_org_plugin_number_idx');
        });
    }

    public function down(): void {
        Schema::dropIfExists('accounting_articles');
    }
};
