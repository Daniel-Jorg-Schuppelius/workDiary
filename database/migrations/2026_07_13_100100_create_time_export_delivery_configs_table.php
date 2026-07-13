<?php
/*
 * Created on   : Mon Jul 13 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_07_13_100100_create_time_export_delivery_configs_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Automatische Export-Lieferung je Organisation × Export-Profil
 * (A21 · MVP-019, ../WorkDiary-Architecture/zeit-export.md §11 „Automatische Lieferung"):
 * optionaler E-Mail-Versand (Empfängerliste) und/oder SFTP-Upload beim
 * Export-Abschluss. SFTP-Passwort liegt at-rest verschlüsselt
 * (`encrypted`-Cast, APP_KEY); leere Strings werden als NULL gespeichert.
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('time_export_delivery_configs', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $t->string('profile', 32);                          // config/exports.php-Schlüssel
            $t->boolean('mail_enabled')->default(false);
            $t->json('mail_recipients')->nullable();            // Liste validierter Adressen
            $t->boolean('sftp_enabled')->default(false);
            $t->string('sftp_host', 190)->nullable();
            $t->unsignedSmallInteger('sftp_port')->default(22);
            $t->string('sftp_username', 190)->nullable();
            $t->text('sftp_password')->nullable();              // encrypted-Cast (nie Klartext)
            $t->string('sftp_root', 190)->nullable();           // Zielverzeichnis, leer = Home
            $t->timestamps();

            $t->unique(['organization_id', 'profile'], 'tedc_org_profile_uq');
        });
    }

    public function down(): void {
        Schema::dropIfExists('time_export_delivery_configs');
    }
};
