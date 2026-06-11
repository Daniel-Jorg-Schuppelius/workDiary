<?php
/*
 * Created on   : Wed Jun 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_06_10_180100_create_form_submissions_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('form_submissions', function (Blueprint $table): void {
            $table->id();
            // Eigene organization_id (KEIN transitives Scoping über die
            // Vorlage): Submissions werden direkt gelistet/gefiltert.
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('form_template_id')->constrained('form_templates')->cascadeOnDelete();
            // Kopie der Felddefinition zum Ausfüllzeitpunkt (Versionssicherheit:
            // spätere Vorlagen-Änderungen verändern alte Nachweise NICHT).
            $table->json('fields_snapshot');
            $table->json('values');
            // Optionaler Bezug: DiaryEntry | Customer | Asset | Project.
            $table->nullableMorphs('subject', 'form_sub_subject_idx');
            $table->foreignId('submitted_by_user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('submitted_at');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['organization_id', 'form_template_id'], 'form_sub_org_tpl_idx');
            $table->index(['organization_id', 'submitted_at'], 'form_sub_org_at_idx');
        });
    }

    public function down(): void {
        Schema::dropIfExists('form_submissions');
    }
};
