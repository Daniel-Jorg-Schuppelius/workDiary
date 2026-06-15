<?php
/*
 * Created on   : Mon Jun 15 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_06_15_310000_add_tenant_status_to_organizations.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SaaS-Mandantenstatus (Feature 021): explizit setzbarer Vertragsstatus
 * (trial|active|suspended) je Organisation. NULL = aus Testphase, Aktiv-Flag
 * und Lizenz-Ablauf abgeleitet (siehe Organization::tenantStatus()).
 */
return new class extends Migration {
    public function up(): void {
        Schema::table('organizations', function (Blueprint $table): void {
            $table->string('tenant_status', 16)->nullable()->after('is_active');
        });
    }

    public function down(): void {
        Schema::table('organizations', function (Blueprint $table): void {
            $table->dropColumn('tenant_status');
        });
    }
};
