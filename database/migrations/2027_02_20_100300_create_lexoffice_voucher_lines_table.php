<?php
/*
 * Created on   : Fri Sep 04 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_02_20_100300_create_lexoffice_voucher_lines_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Feature 152 (MVP-760) = Feature 140 Schnitt 2: Positionen der gespiegelten
 * Lexoffice-Rechnungen. Nur Lexoffice-eigene Rechnungen (`invoice`) tragen
 * Positionen; Belegtexte und Empfänger kommen mit (Endkunde bei Partner-
 * rechnungen steht im Text).
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('lexoffice_voucher_lines', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $t->foreignId('voucher_id')->constrained('lexoffice_vouchers')->cascadeOnDelete();
            $t->unsignedSmallInteger('position');
            $t->string('type', 16)->nullable();                 // custom|service|material
            $t->string('external_article_id', 64)->nullable();  // Lexoffice-Artikel-UUID der Position
            $t->foreignId('lexoffice_article_id')->nullable()->constrained('lexoffice_articles')->nullOnDelete();
            $t->string('name', 255);
            $t->text('description')->nullable();
            $t->decimal('quantity', 12, 3)->default(1);
            $t->string('unit_name', 32)->nullable();
            $t->decimal('unit_net', 12, 4);
            $t->decimal('total_net', 12, 2);
            $t->decimal('tax_rate', 5, 2)->nullable();
            $t->char('currency', 3)->default('EUR');
            $t->timestamps();

            $t->unique(['voucher_id', 'position'], 'lex_voucher_lines_voucher_pos_uq');
            $t->index(['organization_id', 'lexoffice_article_id'], 'lex_voucher_lines_org_article_idx');
        });

        Schema::table('lexoffice_vouchers', function (Blueprint $t): void {
            $t->text('voucher_text')->nullable()->after('payload');      // Titel + Einleitung + Schlusstext
            $t->string('recipient_name', 255)->nullable()->after('voucher_text');
            $t->timestamp('lines_synced_at')->nullable()->after('recipient_name');
        });
    }

    public function down(): void {
        Schema::table('lexoffice_vouchers', function (Blueprint $t): void {
            $t->dropColumn(['voucher_text', 'recipient_name', 'lines_synced_at']);
        });
        Schema::dropIfExists('lexoffice_voucher_lines');
    }
};
