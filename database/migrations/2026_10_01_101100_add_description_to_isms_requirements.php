<?php
/*
 * Created on   : Wed Jul 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_10_01_101100_add_description_to_isms_requirements.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * OSCAL-Katalog-Import (Nachtrag 044a, AR §24): frei lizenzierte Kataloge
 * (NIST SP 800-53/CSF public domain, BSI Stand-der-Technik CC BY-SA) dürfen
 * mit Volltext eingebettet werden — `description` trägt die Control-Prosa.
 * ISO-Kataloge bleiben bewusst Titel-only (Urheberrecht, DIN-FAQ).
 */
return new class extends Migration {
    public function up(): void {
        Schema::table('isms_requirements', function (Blueprint $table): void {
            $table->text('description')->nullable()->after('title');
        });
    }

    public function down(): void {
        Schema::table('isms_requirements', function (Blueprint $table): void {
            $table->dropColumn('description');
        });
    }
};
