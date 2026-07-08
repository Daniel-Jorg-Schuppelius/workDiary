<?php
/*
 * Created on   : Sun Jul 05 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_09_11_100000_create_caldav_connections_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * CalDAV-Anbindung je Organisation (Feature 058, MVP-126): Basis-URL,
 * Zugangsdaten (App-Passwort, verschlüsselt at-rest, APP_KEY!) und der
 * Ziel-Kalenderpfad (Collection). WorkDiary publiziert Termine idempotent
 * über stabile UIDs dorthin; `last_published_at` trägt den Publish-Zeitpunkt.
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('caldav_connections', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations', indexName: 'caldavconn_org_fk')->cascadeOnDelete();
            $table->string('name');
            $table->string('base_url');                 // z. B. https://cloud.example.com/remote.php/dav
            $table->string('username');
            $table->text('app_password');               // encrypted at-rest
            $table->string('calendar_path');            // Collection relativ zur base_url, z. B. calendars/team/dienstplan
            $table->boolean('active')->default(true);
            $table->timestamp('last_published_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users', indexName: 'caldavconn_creator_fk')->nullOnDelete();
            $table->timestamps();

            $table->index('organization_id', 'caldavconn_org_idx');
        });
    }

    public function down(): void {
        Schema::dropIfExists('caldav_connections');
    }
};
