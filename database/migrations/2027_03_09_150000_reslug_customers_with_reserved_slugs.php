<?php
/*
 * Created on   : Sun Oct 04 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_03_09_150000_reslug_customers_with_reserved_slugs.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * MVP-1073 (Nachtrag): Kunden-Kürzel, die mit der Projekt-URL kollidieren
 * (`intern` als Platzhalter, feste Pfade unter /projects), bekommen ein freies
 * Kürzel. Das Kürzel steht nur in der Projekt-URL; Kundenseiten laufen über Sqid.
 */
return new class extends Migration {
    /** Stand von Customer::RESERVED_SLUGS — bewusst als Kopie, Migrationen hängen nicht am App-Code. */
    private const RESERVED = ['intern', 'create', 'duplicates', 'times'];

    public function up(): void {
        $customers = DB::table('customers')->whereIn('slug', self::RESERVED)->orderBy('id')->get(['id', 'organization_id', 'slug']);
        foreach ($customers as $customer) {
            $suffix = 2;
            do {
                $candidate = $customer->slug . '-' . $suffix++;
            } while (DB::table('customers')->where('organization_id', $customer->organization_id)->where('slug', $candidate)->exists());

            DB::table('customers')->where('id', $customer->id)->update(['slug' => $candidate]);
        }
    }

    public function down(): void {
        // Nicht umkehrbar: die alten Kürzel kollidieren mit der Projekt-URL.
    }
};
