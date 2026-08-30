<?php
/*
 * Created on   : Sat Aug 29 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_02_19_105900_create_learning_content_translations.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Übersetzte Kursinhalte (Feature 149, MVP-748).
 *
 * Eine Zeile je Sprache — wie bei `help_topics`. **Kein zweiter Kurs:**
 * eine Übersetzung ist eine Lesehilfe an derselben Kursversion, damit es
 * bei einem Kurs, einer Freigabe und einem Nachweis bleibt.
 *
 * **Maßgeblich bleibt die Ausgangssprache.** Eine maschinelle Übersetzung
 * einer Sicherheitsunterweisung darf nicht unbesehen als Nachweis gelten —
 * deshalb wird sie erst nach **Freigabe durch einen Menschen** angezeigt,
 * und der Kurs sagt sichtbar, dass er übersetzt ist.
 *
 * `source_hash` bindet die Übersetzung an den Stand, aus dem sie entstand:
 * ändert sich der Stoff, ist sie veraltet und wird nicht mehr ausgespielt.
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('learning_content_translations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            // Kurs oder Lerneinheit.
            $table->string('translatable_type', 120);
            $table->unsignedBigInteger('translatable_id');
            $table->string('locale', 8);
            // Übersetzte Felder als Struktur (title, subtitle, blocks …).
            $table->longText('payload');
            // Prüfsumme des Ausgangsstands — macht Veralterung erkennbar.
            $table->string('source_hash', 64);
            // draft|approved — nur Freigegebenes wird Lernenden gezeigt.
            $table->string('status', 10)->default('draft');
            $table->string('provider', 60)->nullable();
            $table->foreignId('approved_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();

            $table->unique(['translatable_type', 'translatable_id', 'locale'], 'lrn_trans_uq');
            $table->index(['organization_id', 'locale'], 'lrn_trans_org_locale_idx');
        });
    }

    public function down(): void {
        Schema::dropIfExists('learning_content_translations');
    }
};
