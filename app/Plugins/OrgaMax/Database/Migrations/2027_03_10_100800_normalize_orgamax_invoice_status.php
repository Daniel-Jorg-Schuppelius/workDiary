<?php
/*
 * Created on   : Tue Oct 06 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_03_10_100800_normalize_orgamax_invoice_status.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\{DB, Log};

/**
 * Normalisiert `orgamax_invoices.invoice_status` auf die Werte des Plugin-Enums
 * `OrgaMaxInvoiceStatus` (Konsolidierungs-Audit 2026-10, vierte Runde). Die
 * Datenmigration 2027_01_16 übernahm den Status ungeprüft aus den Nutzlasten;
 * seit dem Enum-Cast sprengte jeder fremde Wert (auch NULL und '') das Laden
 * der Zeile. Die vorgefundenen Werte landen im Log, alles außerhalb der Menge
 * wird „unknown“. Die Spalte bleibt nullable varchar(32); nur Werte ändern sich.
 */
return new class extends Migration {
    /**
     * Werte von Orgamax\Enums\InvoiceState zum Zeitpunkt der Migration plus
     * `unknown` — eingefroren statt Enum-Verweis, damit die Migration auch nach
     * einer Enum-Erweiterung dasselbe tut.
     *
     * @var list<string>
     */
    private const KNOWN = ['draft', 'locked', 'partiallyPaid', 'paid', 'cancelled', 'unknown'];

    public function up(): void {
        $found = DB::table('orgamax_invoices')->distinct()->orderBy('invoice_status')->pluck('invoice_status')->all();

        $normalized = DB::table('orgamax_invoices')
            ->where(function (Builder $query): void {
                $query->whereNull('invoice_status')->orWhereNotIn('invoice_status', self::KNOWN);
            })
            ->update(['invoice_status' => 'unknown']);

        Log::info('orgaMAX: invoice_status auf das Plugin-Enum normalisiert', ['found' => $found, 'normalized' => $normalized]);
    }

    /** Datenmigration — die Altwerte sind nicht rekonstruierbar. */
    public function down(): void {}
};
