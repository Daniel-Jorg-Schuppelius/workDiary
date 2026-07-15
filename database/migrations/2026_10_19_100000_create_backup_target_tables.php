<?php
/*
 * Created on   : Tue Jul 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_10_19_100000_create_backup_target_tables.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Verschlüsselte Cloud-Backupziele (Feature 017, Phase 32, MVP-361).
 *
 * Alle drei Tabellen sind SYSTEMWEIT (bewusst ohne organization_id):
 * Backups sichern die gesamte Installation und werden ausschließlich vom
 * Plattform-Admin verwaltet — Allow-List-Einträge in
 * {@see \Tests\Unit\Architecture\TenantTraitCoverageTest}.
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('backup_target_connections', function (Blueprint $table) {
            $table->id();
            $table->string('provider', 32); // BackupProvider (dropbox|microsoft|google)
            $table->string('name');
            $table->string('external_account_id')->nullable();
            $table->string('external_account_label')->nullable();
            $table->text('access_token')->nullable();  // encrypted cast
            $table->text('refresh_token')->nullable(); // encrypted cast
            $table->timestamp('token_expires_at')->nullable();
            $table->text('granted_scopes')->nullable(); // array cast
            $table->string('root_folder_ref')->nullable(); // providerstabile Ref des Backupbereichs
            $table->unsignedBigInteger('quota_total')->nullable();
            $table->unsignedBigInteger('quota_used')->nullable();
            $table->timestamp('quota_checked_at')->nullable();
            $table->string('status', 32)->default('draft'); // BackupTargetStatus
            $table->string('last_error', 300)->nullable();
            $table->timestamp('last_error_at')->nullable();
            $table->unsignedSmallInteger('consecutive_failures')->default(0);
            $table->timestamp('disabled_at')->nullable();
            $table->foreignId('created_by_user_id')->nullable()
                ->constrained('users', indexName: 'btc_creator_fk')->nullOnDelete();
            $table->timestamps();

            $table->index(['provider', 'status'], 'btc_provider_status_ix');
        });

        Schema::create('backup_generations', function (Blueprint $table) {
            $table->id();
            // nullOnDelete: der Nachweis einer Generation überlebt die
            // Trennung/Löschung der Verbindung (Konzept §Zieltrennung).
            $table->foreignId('connection_id')->nullable()
                ->constrained('backup_target_connections', indexName: 'bg_connection_fk')->nullOnDelete();
            $table->uuid('snapshot_uuid')->unique('bg_snapshot_uuid_uq');
            $table->string('retention_class', 16); // BackupRetentionClass
            $table->string('status', 32)->default('building'); // BackupGenerationStatus
            // Remote-Prefix (<pseudonym>/<snapshot-uuid>) redundant zur
            // Verbindung, damit Bereinigung nach Trennung möglich bleibt.
            $table->string('remote_prefix')->nullable();
            $table->unsignedBigInteger('plain_size')->nullable();
            $table->unsignedBigInteger('cipher_size')->nullable();
            $table->unsignedInteger('part_count')->default(0);
            $table->char('manifest_sha256', 64)->nullable();
            $table->string('commit_remote_ref')->nullable();
            // Envelope = bereits verschlüsselter Datenschlüssel (secretbox
            // unter BACKUP_MASTER_KEY bzw. crypto_box_seal an Recovery-Key);
            // DB-Kopie für Restore-Komfort, maßgeblich ist das Commit-Manifest.
            $table->text('key_envelope')->nullable();
            $table->text('recovery_envelope')->nullable();
            $table->string('app_version', 64)->nullable();
            $table->boolean('legal_hold')->default(false);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('committed_at')->nullable();
            $table->timestamp('last_verified_at')->nullable();
            $table->timestamp('restore_tested_at')->nullable();
            $table->unsignedInteger('restore_rpo_seconds')->nullable();
            $table->unsignedInteger('restore_rto_seconds')->nullable();
            $table->string('last_error', 300)->nullable();
            $table->timestamps();

            $table->index(['status', 'retention_class'], 'bg_status_class_ix');
        });

        Schema::create('backup_generation_parts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('generation_id')
                ->constrained('backup_generations', indexName: 'bgp_generation_fk')->cascadeOnDelete();
            $table->unsignedInteger('part_no');
            $table->unsignedBigInteger('plain_size');
            $table->unsignedBigInteger('cipher_size');
            $table->char('plain_sha256', 64);
            $table->char('cipher_sha256', 64);
            $table->string('remote_ref')->nullable();
            $table->timestamp('uploaded_at')->nullable();
            $table->timestamps();

            $table->unique(['generation_id', 'part_no'], 'bgp_gen_part_uq');
        });
    }

    public function down(): void {
        Schema::dropIfExists('backup_generation_parts');
        Schema::dropIfExists('backup_generations');
        Schema::dropIfExists('backup_target_connections');
    }
};
