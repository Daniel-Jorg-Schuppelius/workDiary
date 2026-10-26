<?php
/*
 * Created on   : Mon Oct 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_04_01_170000_add_invoice_link_to_resale_license_assignments.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Feature 185 (MVP-1427): Verkauf einer Stücklizenz mit Bezug auf die
 * Rechnungsposition des Spiegels (Morph wie `resale_period_links`);
 * `invoice_reference` bleibt als Freitext-Rückfall.
 */
return new class extends Migration {
    public function up(): void {
        Schema::table('resale_license_assignments', function (Blueprint $t): void {
            $t->string('linkable_type', 64)->nullable()->after('invoice_reference');
            $t->unsignedBigInteger('linkable_id')->nullable()->after('linkable_type');
            $t->index(['linkable_type', 'linkable_id'], 'resale_lic_assign_linkable_idx');
        });
    }

    public function down(): void {
        Schema::table('resale_license_assignments', function (Blueprint $t): void {
            $t->dropIndex('resale_lic_assign_linkable_idx');
            $t->dropColumn(['linkable_type', 'linkable_id']);
        });
    }
};
