<?php
/*
 * Created on   : Tue Jun 30 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_08_22_120000_create_project_merge_dismissals_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Merkt Projekt-Paare, die der Anwender im Projekt-Abgleich bewusst als „kein
 * Duplikat" markiert hat. Der {@see \App\Services\ProjectDuplicateFinder} schlägt
 * diese Paare danach nicht mehr vor. Das Paar wird normalisiert (kleinere ID
 * zuerst), damit Reihenfolge keine Rolle spielt.
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('project_merge_dismissals', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->nullable()->constrained('organizations')->nullOnDelete();
            $table->unsignedBigInteger('project_low_id');  // immer die kleinere der beiden IDs
            $table->unsignedBigInteger('project_high_id'); // immer die größere der beiden IDs
            $table->foreignId('dismissed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['project_low_id', 'project_high_id'], 'pmd_pair_unique');
            $table->index('organization_id', 'pmd_org_idx');
        });
    }

    public function down(): void {
        Schema::dropIfExists('project_merge_dismissals');
    }
};
