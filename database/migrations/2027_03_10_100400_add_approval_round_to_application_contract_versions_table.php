<?php
/*
 * Created on   : Tue Oct 06 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_03_10_100400_add_approval_round_to_application_contract_versions_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Eine Freigabe gilt einer Version: jede Vertragsversion nennt die
 * Freigaberunde, unter der sie steht. Daraus liest die Akte, welche Version
 * eine frühere Runde abgelöst hat.
 */
return new class extends Migration {
    public function up(): void {
        Schema::table('application_contract_versions', function (Blueprint $table): void {
            $table->unsignedSmallInteger('approval_round')->default(1)->after('version');
        });
    }

    public function down(): void {
        Schema::table('application_contract_versions', function (Blueprint $table): void {
            $table->dropColumn('approval_round');
        });
    }
};
