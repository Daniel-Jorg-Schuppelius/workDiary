<?php
/*
 * Created on   : Wed May 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_07_15_120200_create_remote_pending_sessions_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fernwartungs-Verbindungen, deren Geräte-ID (noch) keinem Asset zugeordnet ist.
 * Werden beim Sync abgelegt und über die Admin-Inbox einem Gerät zugewiesen
 * (oder verworfen). So gehen Sitzungen außerhalb des Sync-Fensters nicht verloren.
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('remote_pending_sessions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->nullable()->constrained('organizations')->nullOnDelete();
            $table->string('provider', 32);        // anydesk | teamviewer
            $table->string('remote_id', 191);      // unbekannte Geräte-/Client-ID
            $table->string('session_id', 191);     // anbieterseitige Session-ID
            $table->timestamp('started_at');
            $table->timestamp('ended_at');
            $table->string('note')->nullable();
            $table->string('status', 16)->default('open'); // open | imported | dismissed
            $table->foreignId('time_entry_id')->nullable()->constrained('time_entries')->nullOnDelete();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->unique(['organization_id', 'provider', 'session_id'], 'rps_unique_session');
            $table->index(['organization_id', 'status', 'provider', 'remote_id'], 'rps_group_idx');
        });
    }

    public function down(): void {
        Schema::dropIfExists('remote_pending_sessions');
    }
};
