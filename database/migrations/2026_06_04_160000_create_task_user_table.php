<?php
/*
 * Created on   : Thu Jun 04 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_06_04_160000_create_task_user_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\{DB, Schema};

/**
 * Mehrfach-Zuweisung von Aufgaben an Team-Mitglieder (n:m). Die bestehende
 * Spalte `tasks.assigned_to` bleibt als „primärer" Bearbeiter erhalten und
 * wird beim Speichern auf den ersten Eintrag des Pivots synchronisiert.
 * Bestandsdaten werden hier in den Pivot übernommen.
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('task_user', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('task_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['task_id', 'user_id']);
        });

        // Bestehende Einzel-Zuweisungen in den Pivot übernehmen (nur gültige User).
        $now = Carbon::now();
        DB::table('tasks')
            ->whereNotNull('assigned_to')
            ->whereIn('assigned_to', fn($q) => $q->select('id')->from('users'))
            ->orderBy('id')
            ->select(['id', 'assigned_to'])
            ->chunkById(500, function ($rows) use ($now): void {
                $insert = $rows->map(fn($r) => [
                    'task_id' => $r->id,
                    'user_id' => $r->assigned_to,
                    'created_at' => $now,
                    'updated_at' => $now,
                ])->all();
                if ($insert !== []) {
                    DB::table('task_user')->insert($insert);
                }
            });
    }

    public function down(): void {
        Schema::dropIfExists('task_user');
    }
};
