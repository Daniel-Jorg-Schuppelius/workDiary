<?php
/*
 * Created on   : Sun Nov 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_04_05_100000_restrict_domain_connection_evidence_fks.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};

/**
 * Domain-Anbieter trennen ohne Datenverlust (MVP-1491, ul-02). Trennen ist
 * jetzt ein Statuswechsel; diese FKs sind das Netz darunter. RESTRICT für alles,
 * was der Anbieter kein zweites Mal liefert: Befehlsjournal (wer hat DNS,
 * Kontakte, Transfers geändert), Buchungseinträge (Bezug der Reselling-Einkäufe),
 * quittierte Ereignisse und Anbieterrechnungen.
 *
 * CASCADE bleibt für Reseller-Konten, Domain-, Kontakt- und DNS-Projektionen:
 * der Abgleich liest sie neu, und eine Verbindung mit Nachweisen lässt sich
 * ohnehin nicht mehr löschen.
 *
 * Nur MySQL/MariaDB (Muster `2027_02_19_110300`): SQLite müsste die Tabellen
 * neu aufbauen, und innerhalb der Migrations-Transaktion lässt sich
 * `foreign_keys` nicht abschalten — das Löschen der Alttabelle würde
 * `resale_purchase_entries.domain_accounting_entry_id` nullen. Der Org-Purge
 * bleibt konvergent: alle vier Tabellen tragen `organization_id` und werden in
 * den Retry-Pässen vor den Verbindungen geleert.
 */
return new class extends Migration {
    /** @var list<array{0: string, 1: string}> Tabelle, FK-Name */
    private const array EVIDENCE_FKS = [
        ['domain_provider_commands', 'dpx_connection_fk'],
        ['domain_accounting_entries', 'dae_connection_fk'],
        ['domain_events', 'dev_connection_fk'],
        ['domain_external_invoices', 'dei_connection_fk'],
    ];

    public function up(): void {
        $this->rebind(restrict: true);
    }

    public function down(): void {
        $this->rebind(restrict: false);
    }

    private function rebind(bool $restrict): void {
        if (DB::connection()->getDriverName() === 'sqlite') {
            return;
        }

        foreach (self::EVIDENCE_FKS as [$tableName, $name]) {
            Schema::table($tableName, function (Blueprint $table) use ($name, $restrict): void {
                $table->dropForeign($name);
                $foreign = $table->foreign('connection_id', $name)->references('id')->on('domain_provider_connections');
                $restrict ? $foreign->restrictOnDelete() : $foreign->cascadeOnDelete();
            });
        }
    }
};
