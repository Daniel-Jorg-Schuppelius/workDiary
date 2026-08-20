<?php
/*
 * Created on   : Thu Aug 20 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_01_19_100000_create_supplier_merge_dismissals_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Merkt Lieferanten-Paare, die der Anwender im Abgleich bewusst als „kein
 * Duplikat" markiert hat (Audit 2026-08, W2.3 — analog zu
 * `customer_merge_dismissals`). Das Paar wird normalisiert (kleinere ID
 * zuerst), damit die Reihenfolge keine Rolle spielt. Kurze, explizite
 * Index-Namen (MySQL-64-Zeichen-Limit).
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('supplier_merge_dismissals', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->nullable()->constrained('organizations', indexName: 'smd_org_fk')->nullOnDelete();
            $table->unsignedBigInteger('supplier_low_id');  // immer die kleinere der beiden IDs
            $table->unsignedBigInteger('supplier_high_id'); // immer die größere der beiden IDs
            $table->foreignId('dismissed_by')->nullable()->constrained('users', indexName: 'smd_user_fk')->nullOnDelete();
            $table->timestamps();

            $table->unique(['supplier_low_id', 'supplier_high_id'], 'smd_pair_unique');
            $table->index('organization_id', 'smd_org_idx');
        });
    }

    public function down(): void {
        Schema::dropIfExists('supplier_merge_dismissals');
    }
};
