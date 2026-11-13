<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_04_19_100000_add_period_and_review_to_diary_entries.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Periodenaufträge mit interner Prüfung (Feature 198, MVP-1634/1635).
 *
 * - `period_key`: Periode des Auftrags in der Schreibweise der Buchhaltung
 *   (`2026`, `2026-H1`, `2026-Q3`, `2026-03`), gelesen über `PeriodKeyCast`.
 * - `requires_review`: Prüfpflicht des einzelnen Auftrags; beim Anlegen aus
 *   der Eintragsart übernommen, mit eigenem Recht abschaltbar.
 * - `reviewer_user_id`: benannte prüfende Person (neben dem Recht `order.review`).
 * - `review_submitted_at`/`review_submitter_user_id`: jüngste Vorlage zur
 *   Prüfung — wer vorgelegt hat, prüft nie selbst.
 * - `entry_types.requires_period`: Periodenarten, von denen eine beim Anlegen
 *   Pflicht ist (JSON-Liste aus `PeriodKind`, leer = keine Pflicht).
 * - `entry_types.requires_review`: Prüfpflicht der Eintragsart.
 *
 * Bestand bleibt unverändert: alle neuen Spalten sind leer bzw. false.
 */
return new class extends Migration {
    public function up(): void {
        Schema::table('diary_entries', function (Blueprint $table): void {
            $table->string('period_key', 10)->nullable()->after('due_date');
            $table->boolean('requires_review')->default(false)->after('period_key');
            $table->foreignId('reviewer_user_id')->nullable()->after('requires_review')->constrained('users')->nullOnDelete();
            $table->timestamp('review_submitted_at')->nullable()->after('reviewer_user_id');
            $table->foreignId('review_submitter_user_id')->nullable()->after('review_submitted_at')->constrained('users')->nullOnDelete();
            // Dublettenschutz der Folgeperiode und Filter der Liste.
            $table->index(['organization_id', 'customer_id', 'entry_type_id', 'period_key'], 'diary_period_series_idx');
        });

        Schema::table('entry_types', function (Blueprint $table): void {
            $table->json('requires_period')->nullable()->after('requires_tour');
            $table->boolean('requires_review')->default(false)->after('requires_period');
        });
    }

    public function down(): void {
        Schema::table('entry_types', function (Blueprint $table): void {
            $table->dropColumn(['requires_period', 'requires_review']);
        });

        Schema::table('diary_entries', function (Blueprint $table): void {
            $table->dropIndex('diary_period_series_idx');
            $table->dropConstrainedForeignId('review_submitter_user_id');
            $table->dropConstrainedForeignId('reviewer_user_id');
            $table->dropColumn(['period_key', 'requires_review', 'review_submitted_at']);
        });
    }
};
