<?php
/*
 * Created on   : Sun Jun 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_06_14_100000_create_datev_booking_batches_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * DATEV-Buchungsstapel (Feature 045, „Priorität 2 / Phase 3"): prüfbarer
 * Buchungsstapel (V700) aus gestellten Rechnungen/Gutschriften und freigegebenen
 * Spesen eines abgeschlossenen Zeitraums. Aufbewahrungspflichtig (GoBD/§147 AO)
 * — SoftDeletes statt Hard-Delete, purgeable_on_downgrade = false in
 * config/plans.php. file_hash = SHA-256 der erzeugten CSV.
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('datev_booking_batches', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->unsignedInteger('batch_no')->default(0);     // laufende Nummer je Organisation
            $table->date('period_from');
            $table->date('period_to');
            $table->string('status', 16)->default('draft');      // draft|exported
            $table->string('skr', 8);                            // skr03|skr04 (Snapshot)
            $table->unsignedInteger('advisor_number');           // Beraternummer (Snapshot)
            $table->unsignedInteger('client_number');            // Mandantennummer (Snapshot)
            $table->string('file_path')->nullable();
            $table->string('file_hash', 64)->nullable();         // SHA-256 der CSV
            $table->unsignedInteger('booking_count')->default(0);
            $table->decimal('total_amount', 14, 2)->default(0);
            $table->boolean('finalized_locked')->default(false); // Festschreibekennzeichen-Stand
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('finalized_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['organization_id', 'status'], 'dbb_org_status_idx');
            $table->unique(['organization_id', 'batch_no'], 'dbb_org_batchno_uq');
        });
    }

    public function down(): void {
        Schema::dropIfExists('datev_booking_batches');
    }
};
