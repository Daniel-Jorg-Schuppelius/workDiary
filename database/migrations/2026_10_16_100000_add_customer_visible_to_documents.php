<?php
/*
 * Created on   : Mon Jul 13 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_10_16_100000_add_customer_visible_to_documents.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kundenfreigabe je Dokument (Feature 031/012, Welle D — Dokument-Spiegelung
 * ins Kundenportal). Erst nach expliziter interner Freigabe erscheint ein
 * kunden-/auftragsgebundenes Dokument im Kundenportal (Default aus — nie
 * stillschweigend kundensichtbar). `customer_released_at`/`customer_released_by`
 * halten Zeitpunkt und Freigebenden für die Nachvollziehbarkeit fest.
 */
return new class extends Migration {
    public function up(): void {
        Schema::table('documents', function (Blueprint $table): void {
            $table->boolean('customer_visible')->default(false)->after('sharepoint_mirror_detached');
            $table->timestamp('customer_released_at')->nullable()->after('customer_visible');
            $table->foreignId('customer_released_by')->nullable()->after('customer_released_at')
                ->constrained('users')->nullOnDelete();

            $table->index(['organization_id', 'customer_visible'], 'documents_org_custvis_idx');
        });
    }

    public function down(): void {
        Schema::table('documents', function (Blueprint $table): void {
            $table->dropForeign(['customer_released_by']);
            $table->dropIndex('documents_org_custvis_idx');
            $table->dropColumn(['customer_visible', 'customer_released_at', 'customer_released_by']);
        });
    }
};
