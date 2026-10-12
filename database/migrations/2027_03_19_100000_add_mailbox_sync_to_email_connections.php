<?php
/*
 * Created on   : Mon Oct 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_03_19_100000_add_mailbox_sync_to_email_connections.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\{DB, Schema};

/**
 * Abruf nach Stand statt nach Lesestatus (MVP-1139): `fetch_since` ist der
 * Startpunkt, `sync_cursor` der Abrufstand (IMAP-UID bzw. Graph-Delta-Link).
 * Bestandspostfächer starten am letzten Abruf minus einem Tag, damit beim
 * Deploy nichts rückwirkend durchgearbeitet wird. Dazu die Postfachadresse
 * freigegebener M365-Postfächer (MVP-1141) und die abonnierte Graph-Ressource,
 * damit ein Ordnerwechsel das alte Abo ablöst.
 */
return new class extends Migration {
    public function up(): void {
        if (! Schema::hasColumn('email_connections', 'fetch_since')) {
            Schema::table('email_connections', function (Blueprint $table): void {
                $table->date('fetch_since')->nullable()->after('processed_folder');
                $table->json('sync_cursor')->nullable()->after('fetch_since');
                $table->string('mailbox_address', 190)->nullable()->after('transport');
                $table->string('subscription_resource', 255)->nullable()->after('subscription_id');
            });
        }

        $today = Carbon::today();
        DB::table('email_connections')->whereNull('fetch_since')->orderBy('id')->each(function (object $row) use ($today): void {
            $since = $row->last_polled_at !== null ? Carbon::parse((string) $row->last_polled_at)->subDay()->startOfDay() : $today;
            DB::table('email_connections')->where('id', $row->id)->update(['fetch_since' => $since->toDateString()]);
        });
    }

    public function down(): void {
        Schema::table('email_connections', function (Blueprint $table): void {
            $table->dropColumn(['fetch_since', 'sync_cursor', 'mailbox_address', 'subscription_resource']);
        });
    }
};
