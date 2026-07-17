<?php
/*
 * Created on   : Thu Jul 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_10_22_102000_create_ai_text_suggestions_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * KI-Leistungstexte (Feature 084, MVP-402): Vorschlags-Datenmodell —
 * polymorpher Bezug auf Rechnungs-/Angebotspositionen, Original +
 * Vorschlag, Entscheidung und Herkunft (Verbindung/Provider/Fallback/
 * Cache). Betriebsdaten, kein Beleg: entschiedene Vorschläge werden
 * nach Frist bereinigt. `ai_assisted_at` kennzeichnet Positionen als
 * KI-unterstützt formuliert (intern, nicht auf dem Beleg).
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('ai_text_suggestions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('subject_type', 120);
            $table->unsignedBigInteger('subject_id');
            $table->string('capability', 80);
            $table->text('original')->nullable();
            $table->text('suggestion');
            $table->string('status', 20)->default('proposed');
            $table->foreignId('connection_id')->nullable()
                ->constrained('ai_provider_connections')->nullOnDelete();
            $table->string('provider', 32)->nullable();
            $table->boolean('fallback_used')->default(false);
            $table->boolean('from_cache')->default(false);
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('decided_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();
            $table->index(['organization_id', 'subject_type', 'subject_id', 'status'], 'aits_org_subject_status_idx');
        });

        Schema::table('invoice_items', function (Blueprint $table): void {
            $table->timestamp('ai_assisted_at')->nullable()->after('position');
        });

        Schema::table('quote_items', function (Blueprint $table): void {
            $table->timestamp('ai_assisted_at')->nullable();
        });
    }

    public function down(): void {
        Schema::table('quote_items', function (Blueprint $table): void {
            $table->dropColumn('ai_assisted_at');
        });
        Schema::table('invoice_items', function (Blueprint $table): void {
            $table->dropColumn('ai_assisted_at');
        });
        Schema::dropIfExists('ai_text_suggestions');
    }
};
