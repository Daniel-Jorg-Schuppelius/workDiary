<?php
/*
 * Created on   : Sun Aug 30 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_02_19_110100_widen_issuer_key_for_rsa.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * VC-JWT (Feature 149, MVP-751 Fortsetzung).
 *
 * **Warum ein zweites Verfahren:** Open Badges 3.0 kennt zwei Nachweisformen
 * — `DataIntegrityProof` mit `eddsa-rdfc-2022` und **VC-JWT**. Die erste
 * verlangt RDF-Kanonisierung (RDFC-1.0); dafür gibt es in PHP **keine**
 * Implementierung, und einen Kanonisierungsalgorithmus, dessen Ausgabe
 * direkt in eine Signatur fließt, schreibt man nicht selbst.
 *
 * Also der zweite Weg: **RS256 mit dem öffentlichen Schlüssel als JWK.**
 *
 * Ein RSA-Schlüssel im PEM-Format sprengt die bisherigen 255 Zeichen —
 * deshalb `text`. Die vorhandenen Ed25519-Schlüssel bleiben unberührt:
 * ausgestellte Zertifikate müssen prüfbar bleiben.
 */
return new class extends Migration {
    public function up(): void {
        Schema::table('learning_issuer_keys', function (Blueprint $table): void {
            $table->text('public_key')->change();
        });
    }

    public function down(): void {
        Schema::table('learning_issuer_keys', function (Blueprint $table): void {
            $table->string('public_key', 255)->change();
        });
    }
};
