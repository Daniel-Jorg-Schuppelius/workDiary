<?php
/*
 * Created on   : Sun Jun 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_06_22_120000_create_permits_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Genehmigungs-Register (Permits): behördliche Genehmigungen für Veranstaltungen
 * mit Status, Frist und Nachweis-Dokument (über die polymorphe attachments-Tabelle,
 * meta_type='evidence'). Die Genehmigungsart (permit_type) korrespondiert mit der
 * Klassifikations-Domäne 'permit_type', wird hier aber als freier Code geführt.
 */
return new class extends Migration {
    public function up(): void {
        if (Schema::hasTable('permits')) {
            return;
        }

        Schema::create('permits', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->nullable()->constrained('organizations')->nullOnDelete();
            $table->foreignId('event_id')->nullable()->constrained('events')->nullOnDelete();
            $table->string('title', 200);
            $table->string('permit_type', 60)->nullable();
            $table->string('authority', 200)->nullable();
            $table->string('reference_no', 120)->nullable();
            $table->string('status', 20)->default('required');
            $table->date('applied_at')->nullable();
            $table->date('valid_from')->nullable();
            $table->date('valid_until')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('organization_id', 'permits_org_idx');
            $table->index(['organization_id', 'status'], 'permits_org_status_idx');
            $table->index(['organization_id', 'valid_until'], 'permits_org_deadline_idx');
            $table->index('event_id', 'permits_event_idx');
        });
    }

    public function down(): void {
        Schema::dropIfExists('permits');
    }
};
