<?php
/*
 * Created on   : Sun May 31 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_05_24_120300_create_protocol_events_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('protocol_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('protocol_id')->constrained('protocols')->cascadeOnDelete();
            $table->string('event', 40);
            $table->foreignId('actor_user_id')->constrained('users')->cascadeOnDelete();
            $table->json('payload')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['protocol_id', 'created_at'], 'protocol_events_protocol_idx');
        });
    }

    public function down(): void {
        Schema::dropIfExists('protocol_events');
    }
};
