<?php
/*
 * Created on   : Sat Jul 05 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_09_09_100000_create_zammad_connections_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Zammad-Anbindung je Organisation (Feature 060, MVP-129): Basis-URL, Token
 * (verschlüsselt at-rest, APP_KEY!) und Queue→Projekt-Zuordnung. Tickets einer
 * zugeordneten Queue werden als WorkDiary-Aufgaben importiert; das Ticketsystem
 * bleibt führend. `last_polled_at` trägt den Polling-Aufholpunkt.
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('zammad_connections', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations', indexName: 'zammadconn_org_fk')->cascadeOnDelete();
            $table->string('name');
            $table->string('base_url');
            $table->text('api_token');                 // encrypted at-rest
            $table->text('webhook_secret')->nullable(); // encrypted at-rest; HMAC-Shared-Secret (X-Hub-Signature)
            $table->boolean('active')->default(true);
            $table->foreignId('default_project_id')->nullable()->constrained('projects', indexName: 'zammadconn_proj_fk')->nullOnDelete();
            $table->json('queue_map')->nullable();     // { zammad_group_id: project_id }
            $table->timestamp('last_polled_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users', indexName: 'zammadconn_creator_fk')->nullOnDelete();
            $table->timestamps();

            $table->index('organization_id', 'zammadconn_org_idx');
        });
    }

    public function down(): void {
        Schema::dropIfExists('zammad_connections');
    }
};
