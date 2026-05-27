<?php
/*
 * Created on   : Wed May 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_07_15_120100_create_software_installations_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};

return new class extends Migration {
    public function up(): void {
        Schema::create('software_installations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('asset_id')->constrained('assets')->cascadeOnDelete();
            $table->foreignId('software_id')->constrained('software')->restrictOnDelete();

            $table->string('version', 64)->nullable();
            $table->text('license_key')->nullable();
            $table->unsignedInteger('seats')->nullable();
            $table->date('installed_on')->nullable();
            $table->date('expires_on')->nullable();
            $table->boolean('is_operating_system')->default(false);
            $table->text('notes')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['asset_id'], 'sw_installs_idx_asset');
            $table->index(['software_id'], 'sw_installs_idx_software');
            $table->index(['organization_id', 'expires_on'], 'sw_installs_idx_org_expiry');
        });

        // Genau eine OS-Installation pro Asset. Partial-Unique-Index funktioniert in
        // SQLite und PostgreSQL nativ; in MySQL muss eine generated column verwendet
        // werden. Hier: alle drei Treiber abdecken.
        $driver = DB::connection()->getDriverName();
        if (in_array($driver, ['sqlite', 'pgsql'], true)) {
            DB::statement(
                'CREATE UNIQUE INDEX sw_installs_uniq_os_per_asset '
                    . 'ON software_installations (asset_id) '
                    . 'WHERE is_operating_system = 1'
            );
        } elseif ($driver === 'mysql' || $driver === 'mariadb') {
            // Generated column = asset_id, wenn OS, sonst NULL → UNIQUE ignoriert NULL.
            DB::statement(
                'ALTER TABLE software_installations '
                    . 'ADD COLUMN os_asset_id BIGINT UNSIGNED GENERATED ALWAYS AS '
                    . '(CASE WHEN is_operating_system = 1 THEN asset_id ELSE NULL END) STORED, '
                    . 'ADD UNIQUE INDEX sw_installs_uniq_os_per_asset (os_asset_id)'
            );
        }
    }

    public function down(): void {
        Schema::dropIfExists('software_installations');
    }
};
