<?php
/*
 * Created on   : Fri Sep 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_02_25_160000_add_rental_terms_revision_to_rental_cases.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** MVP-895: gültige unterschriebene Fassung der Mietbedingungen je Verleihvorgang. */
return new class extends Migration {
    public function up(): void {
        Schema::table('rental_cases', function (Blueprint $table): void {
            $table->foreignId('contract_signing_revision_id')->nullable()->after('terms_snapshot')->constrained('contract_signing_revisions')->nullOnDelete();
        });
    }

    public function down(): void {
        Schema::table('rental_cases', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('contract_signing_revision_id');
        });
    }
};
