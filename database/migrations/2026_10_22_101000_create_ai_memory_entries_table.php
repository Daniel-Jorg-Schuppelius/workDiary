<?php
/*
 * Created on   : Thu Jul 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_10_22_101000_create_ai_memory_entries_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * KI-Gedächtnis (Feature 025, MVP-401): kuratiertes Kontextwissen je
 * Organisation — Glossarbegriffe, Stil-/Schreibregeln und Beispielpaare.
 * Ebenen über customer_id (Kunde) bzw. capability (verb-spezifische
 * Regel); beides NULL = Organisationsebene. Einträge sind Fachdaten:
 * auditiert, exportierbar, löschbar; Kunden-Einträge folgen dem
 * Datenlebenszyklus des Kunden (cascadeOnDelete). Kein Fine-Tuning —
 * die Wirkung entsteht ausschließlich durch Prompt-Einspeisung.
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('ai_memory_entries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained('customers')->cascadeOnDelete();
            $table->string('capability', 80)->nullable();
            $table->string('entry_type', 20); // glossary|style_rule|example
            $table->string('term', 120)->nullable();      // glossary: Begriff/Muster
            $table->text('content');                       // Bedeutung | Regel | Zieltext
            $table->text('source_text')->nullable();       // example: Rohtext
            $table->json('translations')->nullable();      // glossary: Zielsprache => Übersetzung
            $table->string('origin', 10)->default('manual'); // manual|learned
            $table->boolean('active')->default(true);
            $table->unsignedInteger('usage_count')->default(0);
            $table->timestamp('last_used_at')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['organization_id', 'customer_id', 'active'], 'aime_org_customer_active_idx');
            $table->index(['organization_id', 'capability'], 'aime_org_capability_idx');
        });
    }

    public function down(): void {
        Schema::dropIfExists('ai_memory_entries');
    }
};
