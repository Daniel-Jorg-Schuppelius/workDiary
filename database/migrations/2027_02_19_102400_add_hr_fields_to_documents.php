<?php
/*
 * Created on   : Tue Aug 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_02_19_102400_add_hr_fields_to_documents.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Digitale Personalakte (Feature 141, MVP-708; Vollscan 2026-08-23, H7):
 * Dokumente am Mitarbeiter (documentable = User) tragen eine HR-Kategorie
 * (HrDocumentCategory) und ein Aufbewahrungsende (users.left_at +
 * Kategorie-Jahre, gesetzt beim Austritt). Index für Akte je Person und
 * den Retention-Scan.
 */
return new class extends Migration {
    public function up(): void {
        Schema::table('documents', function (Blueprint $table): void {
            $table->string('hr_category', 32)->nullable()->after('confidential');
            $table->date('retention_until')->nullable()->after('hr_category');
            $table->index(['documentable_type', 'documentable_id', 'hr_category'], 'documents_hr_idx');
        });
    }

    public function down(): void {
        Schema::table('documents', function (Blueprint $table): void {
            $table->dropIndex('documents_hr_idx');
            $table->dropColumn(['hr_category', 'retention_until']);
        });
    }
};
