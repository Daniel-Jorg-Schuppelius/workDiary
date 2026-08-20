<?php
/*
 * Created on   : Thu Aug 20 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_01_20_100000_create_article_merge_dismissals_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Merkt Artikel-Paare, die der Anwender im Abgleich bewusst als „kein
 * Duplikat" markiert hat (Audit 2026-08, W2.9 — analog zu
 * `customer_merge_dismissals`). Paar normalisiert (kleinere ID zuerst);
 * kurze, explizite Index-Namen (MySQL-64-Zeichen-Limit).
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('article_merge_dismissals', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->nullable()->constrained('organizations', indexName: 'amd_org_fk')->nullOnDelete();
            $table->unsignedBigInteger('article_low_id');
            $table->unsignedBigInteger('article_high_id');
            $table->foreignId('dismissed_by')->nullable()->constrained('users', indexName: 'amd_user_fk')->nullOnDelete();
            $table->timestamps();

            $table->unique(['article_low_id', 'article_high_id'], 'amd_pair_unique');
            $table->index('organization_id', 'amd_org_idx');
        });
    }

    public function down(): void {
        Schema::dropIfExists('article_merge_dismissals');
    }
};
