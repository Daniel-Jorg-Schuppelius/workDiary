<?php
/*
 * Created on   : Mon May 18 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_05_18_140000_create_sick_leaves_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('sick_leaves', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('start_date');
            $table->date('end_date');
            // initial | follow_up
            $table->string('kind', 20)->default('initial');
            $table->foreignId('follow_up_for_id')->nullable()->constrained('sick_leaves')->nullOnDelete();
            $table->string('au_number', 100)->nullable();
            $table->string('doctor_name', 255)->nullable();
            $table->text('note')->nullable();
            $table->timestamp('kasse_notified_at')->nullable();
            $table->timestamp('reported_at')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancel_reason', 500)->nullable();
            $table->timestamps();

            $table->index(['user_id', 'start_date', 'end_date']);
            $table->index(['start_date', 'end_date']);
            $table->index('follow_up_for_id');
        });
    }

    public function down(): void {
        Schema::dropIfExists('sick_leaves');
    }
};
