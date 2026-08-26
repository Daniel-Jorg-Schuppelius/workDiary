<?php
/*
 * Created on   : Wed Aug 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_02_19_103600_create_construction_notices_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * VOB/B-Schreiben (Feature 062, MVP-728; Vollscan 2026-08-23, H23):
 * Behinderungsanzeige (§ 6 VOB/B) und Bedenkenanmeldung (§ 4 Abs. 3 VOB/B)
 * als **eine** Schreiben-Akte — die Belegart unterscheidet die beiden
 * (`kind` = RenderDocumentKind-Wert).
 *
 * Bewusst nur diese eine Tabelle (Zuschnitt aus Feature 062): Der
 * Zugangsnachweis bleibt der generische Belegversand (`document_dispatches`,
 * Feature 128) — Datum, Empfaenger, Versandweg und Message-ID stehen dort,
 * nicht hier. `sent_at` ist nur die Projektion des ersten Versands.
 *
 * Kein SoftDelete: ein versendetes Schreiben ist festgeschrieben (§ 6/§ 4
 * VOB/B leben von der Nachweisbarkeit) und die Nummer je Organisation muss
 * lueckenlos bleiben — geloeschte Zeilen wuerden den Unique-Schluessel
 * mitzaehlen (bekannte 1062-Falle). Entwuerfe duerfen hart geloescht werden.
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('construction_notices', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            // Fortlaufende Nummer je Organisation (AssignsSequentialNo).
            $table->unsignedInteger('notice_no');
            // Belegart: construction_obstruction_notice | construction_concern_notice
            $table->string('kind', 40);
            $table->string('status', 16)->default('draft'); // draft|sent|acknowledged

            // Anlass und Kontext — alle optional, weil ein Schreiben auch ohne
            // Tagebucheintrag entstehen kann (z. B. fehlende Vorleistung).
            $table->foreignId('diary_entry_id')->nullable()->constrained('diary_entries')->nullOnDelete();
            $table->foreignId('project_id')->nullable()->constrained('projects')->nullOnDelete();
            $table->foreignId('site_id')->nullable()->constrained('sites')->nullOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            // Wetterlage des Anlasstags: unveraenderlicher Snapshot (Feature 062).
            $table->foreignId('weather_snapshot_id')->nullable()->constrained('weather_snapshots')->nullOnDelete();

            $table->string('recipient_name', 200)->nullable();
            $table->string('recipient_email', 190)->nullable();
            $table->string('subject', 200);
            $table->date('occurred_on');
            $table->text('facts');                              // Sachverhalt
            $table->text('impact_schedule')->nullable();        // Auswirkung Bauzeit
            $table->text('impact_cost')->nullable();            // Auswirkung Kosten
            // Reiner Vermerk — verschiebt KEINE Frist automatisch (Feature 062).
            $table->boolean('claims_time_extension')->default(false);
            $table->string('legal_reference', 120)->nullable(); // Rechtsverweis als Text

            $table->timestamp('sent_at')->nullable();
            $table->timestamp('acknowledged_at')->nullable();
            $table->string('acknowledged_note', 500)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['organization_id', 'notice_no'], 'cnotice_org_no_uq');
            $table->index(['organization_id', 'status'], 'cnotice_org_status_idx');
            $table->index(['organization_id', 'kind', 'occurred_on'], 'cnotice_org_kind_date_idx');
        });
    }

    public function down(): void {
        Schema::dropIfExists('construction_notices');
    }
};
