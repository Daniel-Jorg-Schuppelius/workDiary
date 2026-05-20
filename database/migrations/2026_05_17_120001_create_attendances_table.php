<?php
/*
 * Created on   : Sun May 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_05_17_120001_create_attendances_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Attendance sessions ("Stempel-Intervalle"):
 *
 *  - Represent the authoritative record of when an employee was on the clock.
 *  - One open session per user at a time (ended_at IS NULL).
 *  - TimeEntries reference an attendance to distribute the attended time
 *    across projects / administration / travel / breaks.
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('attendances', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->nullable()
                ->constrained('organizations')->nullOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();

            $table->dateTime('started_at');
            $table->dateTime('ended_at')->nullable();
            $table->date('date'); // local work day for fast aggregation
            $table->unsignedInteger('break_minutes_auto')->default(0);
            $table->unsignedInteger('break_minutes_manual')->default(0);
            $table->unsignedInteger('duration_minutes')->default(0); // net (ended - started - breaks)

            // clock | manual | import | auto_close
            $table->string('source', 16)->default('clock');
            // open | closed | auto_closed | adjusted | cancelled
            $table->string('status', 16)->default('open');

            $table->decimal('started_lat', 10, 7)->nullable();
            $table->decimal('started_lng', 10, 7)->nullable();
            $table->decimal('ended_lat', 10, 7)->nullable();
            $table->decimal('ended_lng', 10, 7)->nullable();
            $table->string('started_device', 64)->nullable();
            $table->string('ended_device', 64)->nullable();

            $table->text('note')->nullable();
            $table->foreignId('closed_by')->nullable()
                ->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()
                ->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()
                ->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->index(['user_id', 'date']);
            $table->index(['organization_id', 'date']);
            $table->index('started_at');
            $table->index('status');
        });

        // Enforce "only one open attendance per user" via a partial index.
        // SQLite and PostgreSQL support partial indexes; MySQL does not.
        $driver = Schema::getConnection()->getDriverName();
        if (in_array($driver, ['sqlite', 'pgsql'], true)) {
            Schema::getConnection()->statement(
                'CREATE UNIQUE INDEX attendances_user_open_unique '
                    . 'ON attendances (user_id) WHERE ended_at IS NULL'
            );
        }
    }

    public function down(): void {
        $driver = Schema::getConnection()->getDriverName();
        if (in_array($driver, ['sqlite', 'pgsql'], true)) {
            Schema::getConnection()->statement(
                'DROP INDEX IF EXISTS attendances_user_open_unique'
            );
        }
        Schema::dropIfExists('attendances');
    }
};
