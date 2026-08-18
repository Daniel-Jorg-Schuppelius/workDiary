<?php
/*
 * Created on   : Tue Aug 18 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_01_12_100000_consolidate_invoice_templates_into_render_profiles.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};

/**
 * MVP-651 (Issue #84): `invoice_templates` gehen im Dokumentdesign-Profilmodell
 * auf. Kopf-/Fußtexte werden versionierter Profilinhalt (`content_texts`),
 * Kunden-Sonderdesigns reguläre Profile mit Kundenzeiger
 * (`customers.document_render_profile_id`). Je Template entsteht ein aktives
 * Profil im expliziten Kompatibilitätsstand: Standardlayout + Clear-Preset
 * entsprechen der heutigen Ausgabe; existiert bereits ein CI-Basisdesign,
 * erbt das Profil und überschreibt nur Texte (und ggf. die Akzentfarbe).
 * Die Alt-Tabelle bleibt als Nachweis stehen (Drop = spätere Aufräum-Migration).
 */
return new class extends Migration {
    public function up(): void {
        Schema::table('customers', function (Blueprint $table): void {
            // Kunden-Sonderdesign: ersetzt customers.invoice_template_id.
            $table->foreignId('document_render_profile_id')->nullable()->after('invoice_template_id')
                ->constrained('document_render_profiles')->nullOnDelete();
        });

        Schema::table('document_render_profiles', function (Blueprint $table): void {
            // Kunden-Sonderprofile nehmen NICHT an der org-weiten Auflösung
            // teil — sie wirken nur über den Kundenzeiger.
            $table->boolean('is_customer_specific')->default(false)->after('is_default');
        });

        Schema::table('document_render_profile_versions', function (Blueprint $table): void {
            // Kopf-/Fußtext der Belege (vormals invoice_templates) — Klartext.
            $table->json('content_texts')->nullable()->after('table_style');
        });

        $this->migrateTemplates();
    }

    public function down(): void {
        Schema::table('customers', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('document_render_profile_id');
        });
        Schema::table('document_render_profiles', function (Blueprint $table): void {
            $table->dropColumn('is_customer_specific');
        });
        Schema::table('document_render_profile_versions', function (Blueprint $table): void {
            $table->dropColumn('content_texts');
        });
    }

    /** Öffentlich für den gezielten Datenpfad-Test (Schema existiert dort bereits). */
    public function migrateTemplates(): void {
        $templates = DB::table('invoice_templates')->orderBy('organization_id')->orderByDesc('is_default')->orderBy('id')->get();
        if ($templates->isEmpty()) {
            return;
        }

        // Aktive CI-Basisdesigns je Organisation — migrierte Profile erben dann.
        $baseByOrg = DB::table('document_render_profiles')
            ->where('is_default', true)
            ->where('status', 'active')
            ->whereNotNull('active_version_id')
            ->pluck('id', 'organization_id');

        foreach ($templates as $template) {
            $hasBase = $baseByOrg->has($template->organization_id);
            $accent = $this->normalizedAccent(is_string($template->accent_color) ? $template->accent_color : null);

            $overrides = ['content_texts'];
            $tableStyle = ['preset' => 'clear', 'overrides' => [], 'use_brand_colors' => false];
            if ($accent !== null) {
                $tableStyle['overrides']['accent_color'] = $accent;
                $overrides[] = 'table_style';
            }

            $profileId = DB::table('document_render_profiles')->insertGetId([
                'organization_id' => $template->organization_id,
                'name' => (string) $template->name,
                'status' => 'active',
                'is_default' => false,
                'is_customer_specific' => ! (bool) $template->is_default,
                'document_kinds' => null,
                // Templates wirkten auf die Vertriebsbelege (Rechnung/Angebot/AB/Mahnung).
                'document_family' => 'sales',
                'priority' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $versionId = DB::table('document_render_profile_versions')->insertGetId([
                'organization_id' => $template->organization_id,
                'document_render_profile_id' => $profileId,
                'version' => 1,
                'status' => 'active',
                'layout' => json_encode(\App\Services\DocumentDesign\RenderProfileService::defaultLayout()),
                'block_rules' => json_encode(\App\Services\DocumentDesign\RenderProfileService::defaultBlockRules()),
                'table_style' => json_encode($tableStyle),
                'content_texts' => json_encode([
                    'header_text' => $template->header_text !== null ? (string) $template->header_text : null,
                    'footer_text' => $template->footer_text !== null ? (string) $template->footer_text : null,
                ]),
                // Mit Basisdesign: erbende Variante (nur Text/Farbe überschrieben);
                // ohne: eigenständiger Kompatibilitätsstand = heutige Ausgabe.
                'override_sections' => $hasBase ? json_encode($overrides) : null,
                'activated_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('document_render_profiles')->where('id', $profileId)->update(['active_version_id' => $versionId]);

            // Kundenzeiger: bisherige Template-Zuordnungen übernehmen.
            DB::table('customers')
                ->where('organization_id', $template->organization_id)
                ->where('invoice_template_id', $template->id)
                ->update(['document_render_profile_id' => $profileId]);
        }
    }

    private function normalizedAccent(?string $value): ?string {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }
        if (! str_starts_with($value, '#')) {
            $value = '#' . $value;
        }
        $value = strtolower($value);
        // Standardfarbe entspricht dem Clear-Preset — kein Override nötig.
        if ($value === '#333' || $value === '#333333') {
            return null;
        }
        if (preg_match('/^#[0-9a-f]{3}$/', $value) === 1) {
            $value = '#' . $value[1] . $value[1] . $value[2] . $value[2] . $value[3] . $value[3];
        }

        return preg_match('/^#[0-9a-f]{6}$/', $value) === 1 ? $value : null;
    }
};
