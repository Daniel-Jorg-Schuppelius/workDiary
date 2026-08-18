<?php
/*
 * Created on   : Tue Aug 18 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_01_10_140000_create_catalog_assignment_rules.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Vorschlagsregeln für Katalogzuordnungen (Feature 109, MVP-640).
 *
 * Ein Betrieb ordnet immer wieder dieselben Leistungen denselben Kostengruppen
 * zu: Erdarbeiten auf 310, Rohbau auf 330. Die Regel hält dieses Wissen fest —
 * **als Vorschlag, nicht als Automatik.** Eine gesetzte Zuordnung trägt
 * `source = 'rule'` und ist damit von einer von Hand gesetzten unterscheidbar;
 * nur so lässt sich später sagen, worauf eine Auswertung eigentlich beruht.
 *
 * Zwei Anknüpfungspunkte, absteigend nach Genauigkeit:
 *
 * - **`work_category`** — der Leistungsbereich der Position (StLB-Nummer). Er
 *   steht in der Datei und ist die verlässlichste Grundlage.
 * - **`keyword`** — ein Wort im Kurztext. Schwächer, aber die einzige
 *   Handhabe, wenn die Vergabestelle keine Leistungsbereiche mitschickt.
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('catalog_assignment_rules', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();

            // Woran die Regel greift.
            $table->string('match_type', 20);
            $table->string('match_value', 200);

            // Was sie vorschlägt: Zielkatalog (Stamm) und Schlüssel.
            $table->foreignId('catalog_registry_id')->constrained('catalog_registries', indexName: 'catrule_reg_fk')->cascadeOnDelete();
            $table->string('code', 40);

            // Reihenfolge: Die erste greifende Regel gewinnt - mehrere
            // Vorschläge für dieselbe Position wären keine Hilfe.
            $table->unsignedInteger('priority')->default(100);
            $table->boolean('active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['organization_id', 'active', 'priority'], 'catrule_org_active_idx');
            $table->index(['organization_id', 'match_type'], 'catrule_org_type_idx');
        });
    }

    public function down(): void {
        Schema::dropIfExists('catalog_assignment_rules');
    }
};
