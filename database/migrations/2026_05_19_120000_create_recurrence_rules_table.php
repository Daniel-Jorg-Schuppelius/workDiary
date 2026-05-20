<?php
/*
 * Created on   : Tue May 19 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_05_19_120000_create_recurrence_rules_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('recurrence_rules', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->nullable()
                ->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('project_id')->nullable()
                ->constrained('projects')->nullOnDelete();
            $table->foreignId('customer_id')->nullable()
                ->constrained('customers')->nullOnDelete();
            $table->foreignId('entry_type_id')->nullable()
                ->constrained('entry_types')->nullOnDelete();
            $table->foreignId('assigned_user_id')->nullable()
                ->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()
                ->constrained('users')->nullOnDelete();

            $table->string('name', 160);

            // Template für den zu erzeugenden Auftrag
            $table->string('title_template', 200)->nullable();
            $table->text('content_template');
            $table->unsignedSmallInteger('default_service_minutes')->nullable();
            $table->string('default_priority', 16)->nullable();
            $table->string('default_location_mode', 16)->default('onsite');

            // Wiederholungs-Definition (eigenes, schlankes Schema statt RFC-RRULE)
            $table->string('frequency', 16); // daily | weekly | monthly | yearly
            $table->unsignedSmallInteger('interval')->default(1);
            // Wochentage als CSV (MO,TU,WE,TH,FR,SA,SU); nur bei frequency=weekly
            $table->string('byweekday', 32)->nullable();
            // Monatstag (1-31); nur bei frequency=monthly / yearly
            $table->unsignedTinyInteger('bymonthday')->nullable();
            // Monat (1-12); nur bei frequency=yearly
            $table->unsignedTinyInteger('bymonth')->nullable();

            $table->date('starts_on');
            $table->date('ends_on')->nullable(); // null = unbefristet
            $table->date('last_generated_until')->nullable();

            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->index(['organization_id', 'is_active']);
            $table->index('project_id');
            $table->index('last_generated_until');
        });

        Schema::table('diary_entries', function (Blueprint $table): void {
            $table->foreignId('recurrence_rule_id')->nullable()->after('tour_position')
                ->constrained('recurrence_rules')->nullOnDelete();
            $table->index('recurrence_rule_id', 'de_recurrence_rule_idx');
        });
    }

    public function down(): void {
        Schema::table('diary_entries', function (Blueprint $table): void {
            $table->dropIndex('de_recurrence_rule_idx');
            $table->dropConstrainedForeignId('recurrence_rule_id');
        });

        Schema::dropIfExists('recurrence_rules');
    }
};
