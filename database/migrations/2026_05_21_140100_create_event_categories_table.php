<?php
/*
 * Created on   : Thu May 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_05_21_140100_create_event_categories_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('event_categories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->nullable()
                ->constrained('organizations')->cascadeOnDelete();

            $table->string('name', 160);
            $table->string('slug', 160)->nullable();
            $table->string('color', 9)->nullable();
            $table->text('description')->nullable();

            $table->boolean('requires_certificate')->default(false);
            $table->unsignedSmallInteger('certificate_valid_months')->nullable();

            // Default-Reminder-Offsets in Minuten vor Event-Start; null = Fallback
            // aus config/events.php verwenden.
            // Beispiel: [10080, 1440, 60]  (= 7 Tage, 1 Tag, 1 Stunde vorher)
            $table->json('reminder_offsets')->nullable();

            $table->boolean('is_active')->default(true);

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['organization_id', 'is_active']);
            $table->unique(['organization_id', 'slug']);
        });
    }

    public function down(): void {
        Schema::dropIfExists('event_categories');
    }
};
