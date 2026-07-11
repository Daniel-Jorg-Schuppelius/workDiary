<?php
/*
 * Created on   : Fri Jul 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_10_04_100000_create_asset_blocks_tables.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Entscheidung D12 (Phasen 25–27): EIN gemeinsames Asset-Sperr-/Freigabemodell
 * für Verleih, Disposition und Prüfwesen. Sperren tragen Grund, Quelle als
 * Referenz (Prüfpflicht, Verleihschaden, Arbeitsschutz, manuell), Befristung
 * und auditierte Ausnahmefreigaben. Die bestandsbezogene Lager-/RMA-Quarantäne
 * bleibt getrennt (Mengen statt Geräte). Erfüllt zugleich die in Feature 075
 * vorgesehenen Entitäten asset_compliance_blocks/asset_compliance_exceptions.
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('asset_blocks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('asset_id')->constrained()->cascadeOnDelete();
            $table->string('reason', 30);
            // Quelle der Sperre (z. B. Prüfpflicht, Rücknahmeprüfung, Reklamation)
            $table->string('source_type')->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->text('note')->nullable();
            $table->dateTime('blocked_from');
            $table->date('blocked_until')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('released_at')->nullable();
            $table->foreignId('released_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('release_note')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'asset_id', 'released_at'], 'asset_blocks_org_asset_rel_idx');
            $table->index(['source_type', 'source_id'], 'asset_blocks_source_idx');
        });

        Schema::create('asset_block_exceptions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('asset_block_id')->constrained()->cascadeOnDelete();
            // Einsatzkontext der Ausnahme (rental, dispatch, inspection, …)
            $table->string('context', 30);
            $table->text('reason_text');
            $table->date('valid_until');
            $table->foreignId('granted_by')->constrained('users')->cascadeOnDelete();
            $table->dateTime('revoked_at')->nullable();
            $table->foreignId('revoked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['organization_id', 'asset_block_id'], 'asset_block_exc_org_block_idx');
        });
    }

    public function down(): void {
        Schema::dropIfExists('asset_block_exceptions');
        Schema::dropIfExists('asset_blocks');
    }
};
