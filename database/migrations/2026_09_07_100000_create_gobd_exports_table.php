<?php
/*
 * Created on   : Sat Jul 05 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_09_07_100000_create_gobd_exports_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * GoBD-Datenträgerüberlassung Z3 (Feature 063, MVP-132): revisionssicherer
 * Nachweis je erzeugtem Exportpaket — Zeitraum, gewählte Bereiche, SHA-256 je
 * Datei und über das Paket, ausführende Person. Über das Auditable-Trait in die
 * Hash-Kette (`audit_logs`) eingehängt; die Zeile selbst ist der wiederholbare
 * Nachweis (gleicher Zeitraum ⇒ gleicher Paket-Hash, sofern nichts nacherfasst).
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('gobd_exports', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations', indexName: 'gobdexp_org_fk')->cascadeOnDelete();
            $table->date('period_from');
            $table->date('period_to');
            $table->json('sections');            // gewählte Datenbereiche (Schlüssel)
            $table->json('file_hashes');         // { dateiname: sha256 }
            $table->string('package_sha256', 64);
            $table->unsignedInteger('record_count')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users', indexName: 'gobdexp_creator_fk')->nullOnDelete();
            $table->timestamps();

            $table->index(['organization_id', 'period_from', 'period_to'], 'gobdexp_org_period_idx');
        });
    }

    public function down(): void {
        Schema::dropIfExists('gobd_exports');
    }
};
