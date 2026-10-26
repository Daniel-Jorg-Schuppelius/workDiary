<?php
/*
 * Created on   : Mon Oct 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_04_01_130000_add_recurring_templates_to_lexoffice.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Feature 185 (MVP-1409/1410): Serienvorlagen aus `GET /v1/recurring-templates`
 * (nur lesend) als Plan-Quelle des Reselling-Registers, ihre Positionen aus dem
 * Einzelabruf, und je gespiegelter Rechnung der Verweis auf ihre Vorlage
 * (`recurringTemplateId`).
 */
return new class extends Migration {
    public function up(): void {
        if (! Schema::hasTable('lexoffice_recurring_templates')) {
            Schema::create('lexoffice_recurring_templates', function (Blueprint $t): void {
                $t->id();
                $t->foreignId('organization_id')->constrained()->cascadeOnDelete();
                $t->string('external_id', 64);
                $t->string('contact_external_id', 64)->nullable();
                $t->string('contact_name', 255)->nullable();
                $t->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
                $t->string('title', 255)->nullable();
                $t->string('execution_status', 16);
                $t->string('execution_interval', 16)->nullable();
                $t->date('starts_on')->nullable();
                $t->date('ends_on')->nullable();
                $t->date('next_execution_on')->nullable();
                $t->date('last_execution_on')->nullable();
                $t->boolean('is_finalized')->default(false);
                $t->boolean('is_last_execution_failed')->default(false);
                $t->text('last_execution_error')->nullable();
                $t->decimal('total_net', 15, 2)->nullable();
                $t->string('currency', 3)->default('EUR');
                $t->timestamp('remote_updated_at')->nullable();
                $t->timestamp('lines_synced_at')->nullable();
                $t->json('payload')->nullable();
                $t->timestamp('synced_at')->nullable();
                $t->timestamps();
                $t->unique(['organization_id', 'external_id'], 'lex_rec_tpl_org_ext_uq');
                $t->index(['organization_id', 'customer_id'], 'lex_rec_tpl_org_customer_idx');
            });
        }
        if (! Schema::hasTable('lexoffice_recurring_template_lines')) {
            Schema::create('lexoffice_recurring_template_lines', function (Blueprint $t): void {
                $t->id();
                $t->foreignId('organization_id')->constrained()->cascadeOnDelete();
                $t->foreignId('template_id')->constrained('lexoffice_recurring_templates')->cascadeOnDelete();
                $t->unsignedSmallInteger('position');
                $t->string('kind', 16)->nullable();
                $t->string('external_article_id', 64)->nullable();
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
                $t->unique(['template_id', 'position'], 'lex_rec_tpl_line_uq');
            });
        }
        Schema::table('lexoffice_vouchers', function (Blueprint $t): void {
            if (! Schema::hasColumn('lexoffice_vouchers', 'recurring_template_id')) {
                $t->string('recurring_template_id', 64)->nullable()->after('related_voucher_ids');
                $t->index(['organization_id', 'recurring_template_id'], 'lex_voucher_org_rec_tpl_idx');
            }
        });
    }

    public function down(): void {
        Schema::table('lexoffice_vouchers', function (Blueprint $t): void {
            $t->dropIndex('lex_voucher_org_rec_tpl_idx');
            $t->dropColumn('recurring_template_id');
        });
        Schema::dropIfExists('lexoffice_recurring_template_lines');
        Schema::dropIfExists('lexoffice_recurring_templates');
    }
};
