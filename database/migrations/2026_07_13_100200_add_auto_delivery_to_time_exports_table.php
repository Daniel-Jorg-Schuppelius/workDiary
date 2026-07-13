<?php
/*
 * Created on   : Mon Jul 13 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_07_13_100200_add_auto_delivery_to_time_exports_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Liefernachweis der automatischen Export-Lieferung (A21 · MVP-019):
 * je Kanal (mail/sftp) Zeitpunkt und Ziel — geschrieben genau einmal pro
 * Kanal durch {@see \App\Jobs\DeliverTimeExportJob} (Idempotenz-Anker bei
 * Queue-Retries). Der Audit-Verlauf liegt zusätzlich append-only in
 * time_export_events (export.delivered_auto / export.delivery_failed).
 */
return new class extends Migration {
    public function up(): void {
        Schema::table('time_exports', function (Blueprint $t): void {
            $t->json('auto_delivery')->nullable()->after('delivery_note');
        });
    }

    public function down(): void {
        Schema::table('time_exports', function (Blueprint $t): void {
            $t->dropColumn('auto_delivery');
        });
    }
};
