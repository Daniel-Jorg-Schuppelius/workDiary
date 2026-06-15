<?php
/*
 * Created on   : Sat Jun 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_06_14_240200_create_shift_exchanges_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Schichttausch mit Freigabe (Feature 007).
 *
 * Workflow: Mitarbeiter beantragt Abgabe/Tausch der `scheduled_shift_id`
 * (requested) → optional akzeptiert der Zielkollege (accepted) → die
 * Teamleitung gibt frei (approved) bzw. lehnt ab (rejected). Bei Freigabe
 * wechselt die Schicht-Zuordnung (ggf. beidseitig bei echtem Tausch).
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('shift_exchanges', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('scheduled_shift_id')->constrained('scheduled_shifts')->cascadeOnDelete()
                ->comment('Die abzugebende Schicht');
            $table->foreignId('requested_by_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('target_user_id')->nullable()->constrained('users')->nullOnDelete()
                ->comment('Gewünschter Tauschpartner; NULL = offen für alle');
            $table->foreignId('offered_shift_id')->nullable()->constrained('scheduled_shifts')->nullOnDelete()
                ->comment('Gegenschicht bei echtem Tausch');
            $table->string('status', 16)->default('requested')
                ->comment('requested | accepted | approved | rejected | cancelled');
            $table->foreignId('decided_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->string('reason')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['organization_id', 'status'], 'shift_exch_org_status_idx');
            $table->index('scheduled_shift_id', 'shift_exch_shift_idx');
            $table->index(['target_user_id', 'status'], 'shift_exch_target_status_idx');
            $table->index(['requested_by_user_id', 'status'], 'shift_exch_requester_idx');
        });
    }

    public function down(): void {
        Schema::dropIfExists('shift_exchanges');
    }
};
