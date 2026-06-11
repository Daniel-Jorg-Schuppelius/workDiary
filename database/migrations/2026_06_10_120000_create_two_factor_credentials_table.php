<?php
/*
 * Created on   : Tue Jun 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_06_10_120000_create_two_factor_credentials_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};

/**
 * Mehr-Methoden-2FA: pro Nutzer beliebig viele Faktoren (TOTP/E-Mail/WebAuthn).
 * `secret` (TOTP-Geheimnis) und `data` (WebAuthn-Credential JSON) sind at-rest
 * verschluesselt (encrypted-Cast). Recovery-Codes bleiben am User (methodenübergreifend).
 * Migriert bestehende bestaetigte users.two_factor_secret als TOTP-Credential.
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('two_factor_credentials', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('type', 16);                 // totp | email | webauthn
            $table->string('label')->nullable();        // z. B. "iPhone", "YubiKey", E-Mail
            $table->text('secret')->nullable();         // TOTP-Secret (encrypted)
            $table->text('data')->nullable();           // WebAuthn-Credential (encrypted JSON)
            $table->string('credential_id')->nullable(); // WebAuthn credentialId (base64url, für Lookup)
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'type'], 'tfc_user_type_index');
            $table->index('credential_id', 'tfc_credential_id_index');
        });

        // Bestehende bestaetigte TOTP der users-Tabelle als Credential uebernehmen.
        DB::table('users')
            ->whereNotNull('two_factor_secret')
            ->whereNotNull('two_factor_confirmed_at')
            ->orderBy('id')
            ->each(function (object $u): void {
                DB::table('two_factor_credentials')->insert([
                    'user_id' => $u->id,
                    'type' => 'totp',
                    'label' => 'Authenticator',
                    'secret' => $u->two_factor_secret, // bereits verschluesselt gespeichert
                    'confirmed_at' => $u->two_factor_confirmed_at,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            });
    }

    public function down(): void {
        Schema::dropIfExists('two_factor_credentials');
    }
};
