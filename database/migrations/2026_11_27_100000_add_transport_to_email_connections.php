<?php
/*
 * Created on   : Wed Aug 05 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_11_27_100000_add_transport_to_email_connections.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Mail-Eingang über Microsoft Graph (Feature 102, MS365-Plan B): der
 * bestehende IMAP-Eingang bekommt eine Transport-Wahl je Postfach.
 * `msgraph`-Postfächer nutzen die Graph-Mail-Verbindung der Organisation
 * (delegated, Mail.ReadWrite) — Exchange Online hat IMAP-Basic-Auth seit 2023
 * abgeschaltet, M365-Postfächer waren bisher gar nicht anbindbar.
 * Host/Benutzer/Passwort werden dafür nullable (nur IMAP braucht sie).
 */
return new class extends Migration {
    public function up(): void {
        Schema::table('email_connections', function (Blueprint $table): void {
            $table->string('transport', 16)->default('imap')->after('name');
            $table->string('host')->nullable()->change();
            $table->string('username')->nullable()->change();
            $table->text('password')->nullable()->change();
        });
    }

    public function down(): void {
        Schema::table('email_connections', function (Blueprint $table): void {
            $table->dropColumn('transport');
            $table->string('host')->nullable(false)->change();
            $table->string('username')->nullable(false)->change();
            $table->text('password')->nullable(false)->change();
        });
    }
};
