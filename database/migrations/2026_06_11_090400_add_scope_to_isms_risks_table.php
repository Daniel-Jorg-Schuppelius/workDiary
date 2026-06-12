<?php
/*
 * Created on   : Thu Jun 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_06_11_090400_add_scope_to_isms_risks_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Risiken erhalten einen (optionalen) Geltungsbereich (Feature 046).
 * Bestandszeilen werden in der Datenmigration 2026_06_11_090500 auf den
 * Default-Scope der Organisation gebackfillt; neue Risiken bekommen den
 * Default-Scope im RiskService.
 */
return new class extends Migration {
    public function up(): void {
        Schema::table('isms_risks', function (Blueprint $table): void {
            $table->foreignId('isms_scope_id')
                ->nullable()
                ->after('organization_id')
                ->constrained('isms_scopes')
                ->nullOnDelete();
        });
    }

    public function down(): void {
        Schema::table('isms_risks', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('isms_scope_id');
        });
    }
};
