<?php
/*
 * Created on   : Sat Jun 06 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_08_13_000004_create_password_reset_tokens_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Token-Tabelle für „Passwort vergessen". Eigener (self-contained) Reset-Flow,
 * da der Auth-Provider (legacy) Laravels Password-Broker nicht bedient.
 * Token wird gehasht gespeichert; Schlüssel ist die E-Mail.
 */
return new class extends Migration {
    public function up(): void {
        if (Schema::hasTable('password_reset_tokens')) {
            return;
        }
        Schema::create('password_reset_tokens', function (Blueprint $table): void {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void {
        Schema::dropIfExists('password_reset_tokens');
    }
};
