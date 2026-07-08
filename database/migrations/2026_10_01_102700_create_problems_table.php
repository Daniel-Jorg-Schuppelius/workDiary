<?php
/*
 * Created on   : Wed Jul 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_10_01_102700_create_problems_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Feature 065, P6 (MVP-156): Problem-Management — eigenes Objekt mit
 * Ticket-Pivot (kein Ticket-Duplikat), Known-Error-Sichtbarkeit und
 * Wirksamkeitsprüfung mit Frist.
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('problems', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')
                ->constrained('organizations', indexName: 'prb_org_fk')
                ->cascadeOnDelete();
            $table->string('title', 200);
            $table->text('description')->nullable();
            $table->foreignId('owner_id')->nullable()
                ->constrained('users', indexName: 'prb_owner_fk')
                ->nullOnDelete();
            $table->string('status', 20)->default('open'); // open|analyzing|known_error|resolved|closed
            $table->text('root_cause')->nullable();
            $table->text('evidence')->nullable();
            $table->text('workaround')->nullable();
            $table->text('permanent_fix')->nullable();
            $table->string('visibility', 12)->default('internal'); // internal|customer
            $table->timestamp('effectiveness_check_due_at')->nullable();
            $table->timestamp('effectiveness_checked_at')->nullable();
            $table->text('effectiveness_result')->nullable();
            $table->timestamps();
        });

        Schema::create('problem_ticket', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('problem_id')
                ->constrained('problems', indexName: 'prt_problem_fk')
                ->cascadeOnDelete();
            $table->foreignId('service_ticket_id')
                ->constrained('service_tickets', indexName: 'prt_ticket_fk')
                ->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['problem_id', 'service_ticket_id'], 'prt_unique');
        });
    }

    public function down(): void {
        Schema::dropIfExists('problem_ticket');
        Schema::dropIfExists('problems');
    }
};
