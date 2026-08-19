<?php
/*
 * Created on   : Wed Aug 19 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_01_13_100000_drop_invoice_templates_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};

/**
 * MVP-651 (Issue #84), Aufräumschritt: entfernt das abgelöste
 * Rechnungsvorlagen-System. Die Inhalte sind mit `2027_01_12_100000` in
 * Renderprofile überführt (Kopf-/Fußtexte als `content_texts`, Akzentfarbe
 * im Tabellenstil, Kundenzuordnung über `customers.document_render_profile_id`);
 * seither liest kein Codepfad mehr `invoice_templates`.
 *
 * Sicherung statt Blindflug: läuft die Migration auf einer Datenbank, in der
 * die Überführung NICHT stattgefunden hat (Vorlagen vorhanden, aber kein
 * einziges migriertes Profil), bricht sie ab — dann fehlt der Vorlauf.
 */
return new class extends Migration {
    public function up(): void {
        if (! Schema::hasTable('invoice_templates')) {
            return;
        }

        $templates = (int) DB::table('invoice_templates')->count();
        if ($templates > 0) {
            $profiles = (int) DB::table('document_render_profiles')->count();
            if ($profiles === 0) {
                throw new RuntimeException(
                    'Abbruch: ' . $templates . ' Rechnungsvorlagen vorhanden, aber kein einziges Renderprofil — '
                    . 'die Überführung (2027_01_12_100000) ist auf dieser Datenbank nicht gelaufen.',
                );
            }
        }

        if (Schema::hasColumn('customers', 'invoice_template_id')) {
            Schema::table('customers', function (Blueprint $table): void {
                $table->dropConstrainedForeignId('invoice_template_id');
            });
        }

        Schema::dropIfExists('invoice_templates');
    }

    /**
     * Rückbau stellt nur die Struktur wieder her (leere Tabelle, leerer
     * Zeiger) — die Inhalte leben in den Renderprofilen weiter.
     */
    public function down(): void {
        if (! Schema::hasTable('invoice_templates')) {
            Schema::create('invoice_templates', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
                $table->string('name', 120);
                $table->string('slug', 64);
                $table->text('header_text')->nullable();
                $table->text('footer_text')->nullable();
                $table->string('accent_color', 16)->nullable();
                $table->boolean('is_default')->default(false);
                $table->timestamps();

                $table->unique(['organization_id', 'slug']);
                $table->index(['organization_id', 'is_default']);
            });
        }

        if (! Schema::hasColumn('customers', 'invoice_template_id')) {
            Schema::table('customers', function (Blueprint $table): void {
                $table->foreignId('invoice_template_id')->nullable()
                    ->constrained('invoice_templates')->nullOnDelete();
            });
        }
    }
};
