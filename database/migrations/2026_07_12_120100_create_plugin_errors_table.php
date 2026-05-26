<?php
/*
 * Created on   : Tue May 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_07_12_120100_create_plugin_errors_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Plugin-Fehler-Inbox. Jede Eintrag = ein gefangener Throwable während
 * Boot / Runtime / Healthcheck. Wird in der Admin-UI angezeigt
 * (/admin/plugin-errors) zum Reviewen, Acknowledgen, ggf. Reset des
 * Failure-Counters.
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('plugin_errors', function (Blueprint $table): void {
            $table->id();
            $table->string('plugin_id', 64);
            $table->string('phase', 24); // boot | runtime | healthcheck
            $table->string('exception_class', 191)->nullable();
            $table->text('message');
            $table->longText('trace')->nullable();
            $table->json('context')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamp('acknowledged_at')->nullable();
            $table->foreignId('acknowledged_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['plugin_id', 'acknowledged_at']);
            $table->index(['phase', 'occurred_at']);
        });
    }

    public function down(): void {
        Schema::dropIfExists('plugin_errors');
    }
};
