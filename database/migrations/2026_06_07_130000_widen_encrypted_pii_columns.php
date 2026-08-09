<?php
/*
 * Created on   : Sun Jun 07 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_06_07_130000_widen_encrypted_pii_columns.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Verschlüsselte PII-Felder benötigen mehr Platz: Laravels encrypted-Cast
 * erzeugt deutlich längere Werte als das Klartext-Original → text statt string.
 * Die eigentliche Verschlüsselung erfolgt über den Model-Cast (+ Befehl
 * security:encrypt-existing für Bestandsdaten).
 */
return new class extends Migration {
    public function up(): void {
        // SQLite ist dynamisch typisiert (VARCHAR == TEXT, beliebige Länge) → kein
        // Resize nötig; ein change() würde dort nur die Tabelle unnötig neu bauen.
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            return;
        }

        Schema::table('users', function (Blueprint $table): void {
            $table->text('tax_identification_number')->nullable()->change();
            $table->text('social_security_number')->nullable()->change();
        });
        // contact_bank_accounts wird von einer create-Migration mit neuerem
        // Zeitstempel angelegt (die die Spalten bereits als text definiert) — auf
        // frischen Installationen existiert die Tabelle an dieser Stelle noch nicht.
        if (Schema::hasTable('contact_bank_accounts')) {
            Schema::table('contact_bank_accounts', function (Blueprint $table): void {
                $table->text('account_holder')->nullable()->change();
                $table->text('iban')->nullable()->change();
                $table->text('bic')->nullable()->change();
            });
        }
        if (Schema::hasTable('contact_addresses')) {
            Schema::table('contact_addresses', function (Blueprint $table): void {
                $table->text('street')->nullable()->change();
                $table->text('supplement')->nullable()->change();
            });
        }
    }

    public function down(): void {
        // Bewusst kein Rückbau auf string: verschlüsselte Werte würden abgeschnitten.
    }
};
