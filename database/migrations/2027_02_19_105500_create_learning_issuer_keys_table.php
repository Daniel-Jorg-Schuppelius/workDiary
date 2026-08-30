<?php
/*
 * Created on   : Fri Aug 28 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_02_19_105500_create_learning_issuer_keys_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ausstellerschlüssel für verifizierbare Zertifikate (Feature 149, MVP-751).
 *
 * Open Badges 3.0 baut auf W3C Verifiable Credentials auf: das Zertifikat
 * wird signiert und ist damit **ohne Rückfrage prüfbar** — der Unterschied
 * zwischen einem PDF, das sich fälschen lässt, und einem Nachweis, den ein
 * Auftraggeber maschinell verifiziert.
 *
 * Der **private Schlüssel liegt verschlüsselt** (Laravel-Cast `encrypted`,
 * also am APP_KEY). Ein Signaturschlüssel im Klartext in der Datenbank wäre
 * die Fälschungswerkstatt gleich mitgeliefert.
 *
 * Ein Schlüssel wird nie gelöscht, nur widerrufen — sonst ließen sich alte
 * Zertifikate nicht mehr prüfen.
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('learning_issuer_keys', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('algorithm', 20)->default('ed25519');
            $table->string('public_key', 255);
            // encrypted ⇒ text: der Ciphertext ist deutlich länger als das
            // Klartext-Feld (bekannte Falle bei strikten Spaltenbreiten).
            $table->text('private_key');
            $table->string('key_id', 64);
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();

            $table->unique(['organization_id', 'key_id'], 'lrn_issuer_key_uq');
            $table->index(['organization_id', 'revoked_at'], 'lrn_issuer_key_active_idx');
        });
    }

    public function down(): void {
        Schema::dropIfExists('learning_issuer_keys');
    }
};
