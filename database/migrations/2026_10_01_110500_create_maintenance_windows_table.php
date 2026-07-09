<?php
/*
 * Created on   : Wed Jul 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_10_01_110500_create_maintenance_windows_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Feature 022/041, MVP-055: geplante Wartungsfenster (system- oder
 * organisationsweit) mit Ankündigung, optionalem Nur-Lesen-Betrieb und
 * auditiertem Lebenszyklus. Ergänzt den bestehenden sofortigen
 * Org-Wartungsmodus (organizations.settings.maintenance).
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('maintenance_windows', function (Blueprint $table): void {
            $table->id();
            $table->string('scope', 15); // system/organization
            $table->foreignId('organization_id')->nullable()
                ->constrained('organizations', indexName: 'mwin_org_fk')
                ->cascadeOnDelete();
            $table->timestamp('announce_from')->nullable();
            $table->timestamp('starts_at');
            $table->timestamp('ends_at');
            $table->string('message', 300)->nullable();
            $table->boolean('read_only')->default(false);
            $table->boolean('block_ingest')->default(false);
            $table->string('status', 15); // planned/announced/active/completed/extended/rolled_back/cancelled
            $table->foreignId('created_by')->nullable()
                ->constrained('users', indexName: 'mwin_user_fk')
                ->nullOnDelete();
            $table->string('notes', 500)->nullable();
            $table->timestamps();

            $table->index(['status', 'starts_at'], 'mwin_status_start_idx');
        });
    }

    public function down(): void {
        Schema::dropIfExists('maintenance_windows');
    }
};
