<?php
/*
 * Created on   : Thu Nov 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_05_02_100000_add_mail_body_to_incoming_einvoices.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Rechnungseingang (MVP-1812): Text der Herkunfts-Mail, verschlüsselt, zum Nachsehen neben dem Original. */
return new class extends Migration {
    public function up(): void {
        Schema::table('incoming_einvoices', function (Blueprint $table): void {
            $table->longText('mail_body')->nullable();
        });
    }

    public function down(): void {
        Schema::table('incoming_einvoices', function (Blueprint $table): void {
            $table->dropColumn('mail_body');
        });
    }
};
