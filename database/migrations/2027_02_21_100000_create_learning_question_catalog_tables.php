<?php
/*
 * Created on   : Mon Sep 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_02_21_100000_create_learning_question_catalog_tables.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};

/**
 * Fragenkatalog (Feature 149, MVP-782): eine Frage gehört der Organisation,
 * nicht mehr genau einer Prüfung. Die Zugehörigkeit zu Prüfungen liegt in
 * der Zwischentabelle `learning_quiz_question` (mit Position), Ziehregeln
 * („5 aus Brandschutz") in `learning_quiz_draw_rules`.
 *
 * Bestandsfragen wandern in die Zwischentabelle; danach entfällt
 * `learning_questions.learning_quiz_id`. Die Prüfungsakte bleibt der
 * `questions_snapshot` je Versuch — alte Versuche sind davon unberührt.
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('learning_question_categories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('name', 120);
            $table->string('slug', 140);
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();

            $table->unique(['organization_id', 'slug'], 'lrn_qcat_org_slug_uq');
        });

        Schema::create('learning_quiz_question', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('learning_quiz_id')->constrained('learning_quizzes')->cascadeOnDelete();
            $table->foreignId('learning_question_id')->constrained('learning_questions')->cascadeOnDelete();
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();

            $table->unique(['learning_quiz_id', 'learning_question_id'], 'lrn_qq_quiz_question_uq');
            $table->index(['learning_question_id'], 'lrn_qq_question_idx');
        });

        Schema::create('learning_quiz_draw_rules', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('learning_quiz_id')->constrained('learning_quizzes')->cascadeOnDelete();
            $table->foreignId('learning_question_category_id')->constrained('learning_question_categories')->cascadeOnDelete();
            $table->unsignedSmallInteger('count');
            $table->timestamps();

            $table->unique(['learning_quiz_id', 'learning_question_category_id'], 'lrn_draw_quiz_cat_uq');
        });

        // Bestand: jede Frage hängt bisher an genau einer Prüfung.
        $now = now()->toDateTimeString();
        DB::table('learning_questions')
            ->whereNotNull('learning_quiz_id')
            ->orderBy('id')
            ->lazyById(200)
            ->each(function (object $question) use ($now): void {
                DB::table('learning_quiz_question')->insert([
                    'organization_id' => $question->organization_id,
                    'learning_quiz_id' => $question->learning_quiz_id,
                    'learning_question_id' => $question->id,
                    'position' => (int) $question->position,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            });

        // Der alte Index (Prüfung, Position) hängt an der Spalte: auf SQLite
        // scheitert der Spaltenabbau sonst, auf MySQL bliebe ein Restindex —
        // dort trägt der Index aber den Fremdschlüssel, also erst die
        // Constraint, dann der Index, dann die Spalte. SQLite baut die
        // Tabelle beim Spaltenabbau um und nimmt den Fremdschlüssel mit.
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            Schema::table('learning_questions', function (Blueprint $table): void {
                $table->dropIndex('lrn_question_quiz_pos_idx');
            });
            Schema::table('learning_questions', function (Blueprint $table): void {
                $table->dropConstrainedForeignId('learning_quiz_id');
            });
        } else {
            Schema::table('learning_questions', function (Blueprint $table): void {
                $table->dropForeign(['learning_quiz_id']);
                $table->dropIndex('lrn_question_quiz_pos_idx');
            });
            Schema::table('learning_questions', function (Blueprint $table): void {
                $table->dropColumn('learning_quiz_id');
            });
        }

        Schema::table('learning_questions', function (Blueprint $table): void {
            $table->foreignId('learning_question_category_id')->nullable()->after('organization_id')
                ->constrained('learning_question_categories')->nullOnDelete();
            $table->string('title', 180)->nullable()->after('kind');

            $table->index(['organization_id', 'learning_question_category_id'], 'lrn_q_org_cat_idx');
        });
    }

    public function down(): void {
        Schema::table('learning_questions', function (Blueprint $table): void {
            $table->foreignId('learning_quiz_id')->nullable()->after('organization_id')
                ->constrained('learning_quizzes')->cascadeOnDelete();
            $table->index(['learning_quiz_id', 'position'], 'lrn_question_quiz_pos_idx');
        });

        // Erste Zuordnung je Frage zurück in die Spalte — mehr kann die alte
        // Form nicht abbilden.
        DB::table('learning_quiz_question')
            ->orderBy('id')
            ->lazyById(200)
            ->each(function (object $row): void {
                DB::table('learning_questions')
                    ->where('id', $row->learning_question_id)
                    ->whereNull('learning_quiz_id')
                    ->update(['learning_quiz_id' => $row->learning_quiz_id, 'position' => (int) $row->position]);
            });

        Schema::table('learning_questions', function (Blueprint $table): void {
            $table->dropIndex('lrn_q_org_cat_idx');
            $table->dropConstrainedForeignId('learning_question_category_id');
            $table->dropColumn('title');
        });

        Schema::dropIfExists('learning_quiz_draw_rules');
        Schema::dropIfExists('learning_quiz_question');
        Schema::dropIfExists('learning_question_categories');
    }
};
