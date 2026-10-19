<?php
/*
 * Created on   : Mon Oct 19 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_03_28_090000_create_incoming_invoice_templates_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Belegvorlagen mit Zonen (Phase 151, MVP-1219): je Lieferant ein Fingerabdruck
 * aus Kopf und Fuß der ersten Seite und Zonen (relativ zur Seitengröße), aus
 * denen Nummer, Daten und Beträge gelesen werden.
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('incoming_invoice_templates', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('supplier_id')->nullable()->constrained('suppliers')->nullOnDelete();
            $table->string('name', 191);
            // Wörter aus Kopf und Fuß der ersten Seite (normalisiert) — der Wiedererkennungsschlüssel.
            $table->json('fingerprint');
            // Zonen: field, page, x, y, w, h (0–1), parse, source (manual|learned), confirmed.
            $table->json('zones');
            $table->string('source', 16)->default('manual');
            $table->unsignedInteger('hits')->default(0);
            $table->timestamp('last_matched_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['organization_id', 'supplier_id'], 'incoming_invoice_templates_org_supplier_idx');
        });
        Schema::table('incoming_einvoices', function (Blueprint $table): void {
            $table->foreignId('template_id')->nullable()->after('match_kind')
                ->constrained('incoming_invoice_templates', indexName: 'incoming_einvoices_template_fk')->nullOnDelete();
        });
    }

    public function down(): void {
        Schema::table('incoming_einvoices', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('template_id');
        });
        Schema::dropIfExists('incoming_invoice_templates');
    }
};
