<?php
/*
 * Created on   : Mon Aug 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_02_19_101300_add_document_kind_to_invoice_mail_templates.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Generischer Belegversand (Feature 128, MVP-692): Mail-Vorlagen gelten
 * fortan je Belegart (`document_kind`, Werte aus RenderDocumentKind).
 * Bestandszeilen werden per Spalten-Default auf 'invoice' gefüllt —
 * Default-Vorlage gilt weiterhin app-seitig eindeutig, jetzt je (Org, Art).
 */
return new class extends Migration {
    public function up(): void {
        Schema::table('invoice_mail_templates', function (Blueprint $table): void {
            $table->string('document_kind', 40)->default('invoice')->after('name');
            // Neuen Index VOR dem Drop anlegen: er beginnt mit organization_id
            // und übernimmt damit die Index-Pflicht des FK (MariaDB 1553).
            $table->index(['organization_id', 'document_kind', 'is_default'], 'imt_org_kind_default_idx');
            $table->dropIndex(['organization_id', 'is_default']);
        });
    }

    public function down(): void {
        Schema::table('invoice_mail_templates', function (Blueprint $table): void {
            $table->index(['organization_id', 'is_default']);
            $table->dropIndex('imt_org_kind_default_idx');
            $table->dropColumn('document_kind');
        });
    }
};
