<?php
/*
 * Created on   : Sun Nov 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_04_05_100100_rewrite_domain_command_subject_types.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use App\Support\MorphMap;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Nachtrag zu `2027_02_24_100000` (MVP-860): `DomainCommandService::create()`
 * schrieb den Klassennamen in `domain_provider_commands.subject_type`, seit
 * MVP-1491 den Alias. Zeilen dazwischen werden umgeschrieben; idempotent.
 */
return new class extends Migration {
    public function up(): void {
        $legacy = MorphMap::legacy();
        foreach (DB::table('domain_provider_commands')->whereNotNull('subject_type')->distinct()->pluck('subject_type') as $value) {
            $class = $legacy[(string) $value] ?? null;
            if ($class !== null && MorphMap::alias($class) !== $value) {
                DB::table('domain_provider_commands')->where('subject_type', $value)->update(['subject_type' => MorphMap::alias($class)]);
            }
        }
    }

    public function down(): void {
        // Alias bleibt gültig; ein Rückschreiben auf Klassennamen bräche das Lesen.
    }
};
