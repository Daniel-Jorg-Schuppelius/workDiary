<?php
/*
 * Created on   : Fri Jul 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_09_03_100000_create_todoist_connections_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Todoist-Verbindung je Organisation (Feature 055, MVP-111): genau EINE
 * OAuth-Verbindung je Org (unique), Tokens verschlüsselt at-rest
 * (encrypted-Cast, APP_KEY). `last_error` trägt nur die gekürzte
 * Fehlerklasse — nie Payload oder Token. Kurze, explizite FK-/Index-Namen.
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('todoist_connections', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->unique('tdc_org_unique')->constrained('organizations', indexName: 'tdc_org_fk')->cascadeOnDelete();
            $table->string('todoist_user_id')->nullable();
            $table->string('todoist_user_email')->nullable();
            $table->text('access_token')->nullable();   // encrypted-Cast
            $table->text('refresh_token')->nullable();  // encrypted-Cast (Todoist: aktuell ohne Ablauf — Schema hält beides aus)
            $table->timestamp('token_expires_at')->nullable();
            $table->string('scopes')->nullable();
            $table->string('status', 16)->default('active'); // active / paused / disconnected
            $table->boolean('webhook_capable')->default(false);
            $table->string('sync_cursor', 191)->nullable();
            $table->timestamp('last_sync_at')->nullable();
            $table->timestamp('last_full_sync_at')->nullable();
            $table->string('last_error', 191)->nullable(); // gekürzte Fehlerklasse, nie Payload
            $table->foreignId('connected_by')->nullable()->constrained('users', indexName: 'tdc_conn_by_fk')->nullOnDelete();
            $table->timestamp('connected_at')->nullable();
            $table->foreignId('disconnected_by')->nullable()->constrained('users', indexName: 'tdc_disc_by_fk')->nullOnDelete();
            $table->timestamp('disconnected_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void {
        Schema::dropIfExists('todoist_connections');
    }
};
