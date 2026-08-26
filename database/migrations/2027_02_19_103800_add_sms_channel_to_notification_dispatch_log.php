<?php
/*
 * Created on   : Wed Aug 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_02_19_103800_add_sms_channel_to_notification_dispatch_log.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SMS-Kanal (Feature 147, MVP-730 — Vollscan G12).
 *
 * Der Zustellstatus bekommt bewusst KEINE eigene Tabelle: das
 * `notification_dispatch_log` ist bereits der Nachweis, dass eine kritische
 * Meldung rausging, und sein Unique-Key (Org, Ereignis, Subjekt, Stufe) ist
 * mit der Stufe `sms:<userId>` gleichzeitig der Doppelversand-Schutz je
 * Empfänger — bei SMS heißt „doppelt" auch „doppelte Kosten". Eine zweite
 * Tabelle hätte diesen Schutz neu erfinden müssen.
 *
 * Was hier NICHT steht: der Nachrichtentext und die Rufnummer. Beides ist für
 * den Nachweis nicht nötig (Datenminimierung, Art. 5 DSGVO) — die Rufnummer
 * steht ohnehin schon an der Person, der Inhalt in der In-App-Benachrichtigung.
 */
return new class extends Migration {
    public function up(): void {
        Schema::table('notification_dispatch_log', function (Blueprint $table): void {
            $table->string('channel', 16)->nullable()->after('stage');
            $table->foreignId('recipient_user_id')->nullable()->after('recipient_count')
                ->constrained('users', indexName: 'ndl_recipient_fk')->nullOnDelete();
            $table->string('provider', 32)->nullable()->after('recipient_user_id');
            $table->string('provider_message_id', 120)->nullable()->after('provider');
            $table->string('status', 16)->nullable()->after('provider_message_id');
            $table->string('error_code', 64)->nullable()->after('status');
            $table->unsignedSmallInteger('segments')->default(0)->after('error_code');
            $table->timestamp('status_at')->nullable()->after('segments');

            // Monatszähler des Budget-Guards: Segmente je Organisation und Zeitraum.
            $table->index(['organization_id', 'channel', 'created_at'], 'ndl_org_channel_created_idx');
        });
    }

    public function down(): void {
        Schema::table('notification_dispatch_log', function (Blueprint $table): void {
            $table->dropIndex('ndl_org_channel_created_idx');
            $table->dropConstrainedForeignId('recipient_user_id');
            $table->dropColumn(['channel', 'provider', 'provider_message_id', 'status', 'error_code', 'segments', 'status_at']);
        });
    }
};
