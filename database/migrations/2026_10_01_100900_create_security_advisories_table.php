<?php
/*
 * Created on   : Tue Jul 07 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_10_01_100900_create_security_advisories_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Security Advisories (Rang 70): Verwundbarkeits-Hinweise aus der OSV-
 * Datenbank für die installierten Abhängigkeiten (composer.lock +
 * package-lock.json). Installationsweit — bewusst ohne organization_id.
 * `resolved_at` wird gesetzt, sobald ein Advisory beim Pull nicht mehr
 * zurückgeliefert wird (Paket aktualisiert); `statement` trägt die manuelle
 * VEX-Bewertung des Betreibers.
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('security_advisories', function (Blueprint $table): void {
            $table->id();
            $table->string('source', 20)->default('osv'); // osv|manual
            $table->string('external_id', 100)->unique('sa_external_id_uq');
            $table->string('ecosystem', 20); // composer|npm
            $table->string('package', 200);
            $table->string('installed_version', 100);
            $table->string('severity', 20)->default('unknown'); // critical|high|medium|low|unknown
            $table->string('cvss_vector', 150)->nullable();
            $table->string('summary', 500)->nullable();
            $table->string('fixed_in', 100)->nullable();
            $table->text('statement')->nullable(); // manuelle VEX-Bewertung
            $table->dateTime('modified_at')->nullable(); // OSV modified
            $table->dateTime('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['severity', 'resolved_at'], 'sa_severity_resolved_idx');
        });
    }

    public function down(): void {
        Schema::dropIfExists('security_advisories');
    }
};
