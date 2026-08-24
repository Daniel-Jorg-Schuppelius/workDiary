<?php
/*
 * Created on   : Mon Jul 13 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AccessibilityComponentTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\UI;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Blade, View};
use Illuminate\Support\{MessageBag, ViewErrorBag};
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Dauerhaftes Barrierefreiheits-Gate (Feature 038 / B2, MVP-008): sichert die
 * gehärteten Invarianten der zentralen UI-Bausteine, damit ein späterer Umbau
 * sie nicht unbemerkt entfernt. Die statisch prüfbaren WCAG-2.1-AA-Punkte:
 *
 *  - <x-table.th>   rendert scope="col" (bzw. den übergebenen scope),
 *  - <x-table>      reicht eine sr-only <caption> als Tabellenname durch,
 *  - <x-icon-btn>   trägt IMMER einen zugänglichen Namen (label oder Fallback),
 *  - <x-modal>      hat role="dialog", aria-modal und aria-labelledby → Titel,
 *  - Formularfelder koppeln label↔for und melden Fehler via aria-invalid +
 *    aria-describedby, Pflichtfelder via aria-required,
 *  - das app-Layout liefert Sprunglink + <main id="main-content">-Landmark.
 *
 * Die echte Screenreader-/Kontrast-Sichtprüfung bleibt manuell (siehe
 * accessibility-checkliste.md, Abschnitt „manuell, extern").
 */
class AccessibilityComponentTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    /** Leeren Fehler-Bag für Feld-Komponenten teilen (kein Fehlerzustand). */
    private function shareErrors(array $bag = []): void {
        $errors = new ViewErrorBag();
        $errors->put('default', new MessageBag($bag));
        View::share('errors', $errors);
    }

    // ---- <x-table.th> --------------------------------------------------

    public function test_table_th_renders_scope_col_by_default(): void {
        $html = Blade::render('<x-table.th>Name</x-table.th>');

        $this->assertStringContainsString('scope="col"', $html);
        $this->assertStringContainsString('Name', $html);
    }

    public function test_table_th_supports_row_scope(): void {
        $html = Blade::render('<x-table.th scope="row">Zeile</x-table.th>');

        $this->assertStringContainsString('scope="row"', $html);
        $this->assertStringNotContainsString('scope="col"', $html);
    }

    // ---- Sortierbare Spalten (I3): aria-sort + Tastatur ----------------

    public function test_client_sort_th_renders_button_and_aria_sort(): void {
        $html = Blade::render(
            '<x-table table-sort="client"><x-slot:head><tr>'
            . '<x-table.th sort default="desc">Datum</x-table.th>'
            . '<x-table.th sort>Name</x-table.th>'
            . '</tr></x-slot:head><tr><td>x</td><td>y</td></tr></x-table>'
        );

        // Default-Spalte startet sortiert, übrige sortierbare Spalten "none".
        $this->assertStringContainsString('aria-sort="descending"', $html);
        $this->assertStringContainsString('aria-sort="none"', $html);
        // Echter Button im th → Enter/Space lösen den Klick-Handler aus.
        $this->assertStringContainsString('<button type="button"', $html);
        $this->assertStringContainsString('data-sort', $html);
    }

    public function test_server_sort_th_sets_aria_sort_from_current_sort(): void {
        $html = Blade::render(
            '<x-table table-sort="server" route="https://example.test/list" current-sort="name" current-dir="asc">'
            . '<x-slot:head><tr>'
            . '<x-table.th sort="name">Name</x-table.th>'
            . '<x-table.th sort="date">Datum</x-table.th>'
            . '</tr></x-slot:head><tr><td>x</td><td>y</td></tr></x-table>'
        );

        $this->assertStringContainsString('aria-sort="ascending"', $html);
        $this->assertStringContainsString('aria-sort="none"', $html);
        // Das Sortier-Icon ist Deko — Zustand kommt über aria-sort.
        $this->assertStringContainsString('aria-hidden="true"', $html);
    }

    // ---- <x-table caption> ---------------------------------------------

    public function test_table_renders_screenreader_caption(): void {
        $html = Blade::render(
            '<x-table caption="Kundenliste"><x-slot:head><tr><x-table.th>A</x-table.th></tr></x-slot:head><tr><td>x</td></tr></x-table>'
        );

        $this->assertStringContainsString('<caption class="sr-only">Kundenliste</caption>', $html);
    }

    // ---- <x-icon-btn> --------------------------------------------------

    public function test_icon_only_button_always_has_accessible_name(): void {
        // Ohne label + ohne sichtbaren Text MUSS ein Fallback-Name gesetzt sein
        // (nie ein komplett unbeschrifteter Icon-Button).
        $html = Blade::render('<x-icon-btn icon="edit" />');

        $this->assertStringContainsString('aria-label="edit"', $html);
        $this->assertStringContainsString('title="edit"', $html);
    }

    public function test_icon_button_prefers_explicit_label(): void {
        $html = Blade::render('<x-icon-btn icon="delete" label="Löschen" />');

        $this->assertStringContainsString('aria-label="Löschen"', $html);
        $this->assertStringNotContainsString('aria-label="delete"', $html);
    }

    // ---- <x-modal> -----------------------------------------------------

    public function test_modal_has_dialog_role_and_labelledby_title(): void {
        $html = Blade::render('<x-modal title="Test-Dialog" titleId="fixed-title" />');

        $this->assertStringContainsString('role="dialog"', $html);
        $this->assertStringContainsString('aria-modal="true"', $html);
        $this->assertStringContainsString('aria-labelledby="fixed-title"', $html);
        $this->assertStringContainsString('id="fixed-title"', $html);
    }

    public function test_modal_auto_generates_title_id_when_missing(): void {
        $html = Blade::render('<x-modal title="Ohne feste ID" />');

        $this->assertMatchesRegularExpression('/aria-labelledby="(wd-modal-title-[a-f0-9]+)"/', $html);

        preg_match('/aria-labelledby="(wd-modal-title-[a-f0-9]+)"/', $html, $m);
        $this->assertNotEmpty($m[1] ?? null);
        // Die referenzierte ID muss auch am Titel-Element (<h2>) hängen.
        $this->assertStringContainsString('id="' . $m[1] . '"', $html);
    }

    // ---- Formularfelder ------------------------------------------------

    public function test_input_field_couples_label_and_marks_required(): void {
        $this->shareErrors();
        $html = Blade::render('<x-input-field name="email" label="E-Mail" required />');

        $this->assertStringContainsString('for="email"', $html);
        $this->assertStringContainsString('id="email"', $html);
        $this->assertStringContainsString('aria-required="true"', $html);
    }

    public function test_input_field_links_error_and_hint_via_describedby(): void {
        $this->shareErrors(['email' => ['Pflichtangabe']]);
        $html = Blade::render('<x-input-field name="email" label="E-Mail" hint="Firmen-Adresse" />');

        $this->assertStringContainsString('aria-invalid="true"', $html);
        $this->assertStringContainsString('aria-describedby="email-hint email-error"', $html);
        $this->assertStringContainsString('id="email-hint"', $html);
        $this->assertStringContainsString('id="email-error"', $html);
    }

    public function test_input_field_supports_explicit_id_for_repeated_names(): void {
        // I13: gleicher name mehrfach auf der Seite (Loops) → explizite id
        // verhindert doppelte ids; label/for und describedby folgen der id.
        $this->shareErrors();
        $html = Blade::render('<x-input-field name="note" id="swap-note-abc" label="Grund" hint="Kurz halten" />');

        $this->assertStringContainsString('id="swap-note-abc"', $html);
        $this->assertStringContainsString('for="swap-note-abc"', $html);
        $this->assertStringContainsString('name="note"', $html);
        $this->assertStringContainsString('id="swap-note-abc-hint"', $html);
        $this->assertStringNotContainsString('id="note"', $html);
    }

    public function test_select_and_textarea_mark_required_with_aria(): void {
        $this->shareErrors();

        $select = Blade::render('<x-select-field name="kind" label="Art" required><option>x</option></x-select-field>');
        $this->assertStringContainsString('aria-required="true"', $select);
        $this->assertStringContainsString('for="kind"', $select);

        $textarea = Blade::render('<x-textarea-field name="note" label="Notiz" required />');
        $this->assertStringContainsString('aria-required="true"', $textarea);
        $this->assertStringContainsString('for="note"', $textarea);
    }

    // ---- Layout (End-to-End auf gerendertem app-Layout) ----------------

    public function test_app_layout_exposes_skip_link_and_main_landmark(): void {
        $this->setUpOrganization();
        $admin = User::factory()->admin()->create([
            'organization_id' => $this->organization->id,
        ]);
        // Ein Datensatz, damit die Tabelle (statt des Leer-Zustands ohne <thead>)
        // rendert und die scope="col"-Härtung end-to-end greift.
        \App\Models\Customer::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        $html = $this->actingAs($admin)->get(route('customers.index'))
            ->assertOk()
            ->getContent();

        // Sprunglink (WCAG 2.4.1) + Ziel-Landmark.
        $this->assertStringContainsString('href="#main-content"', $html);
        $this->assertStringContainsString(__('Zum Inhalt springen'), $html);
        $this->assertStringContainsString('id="main-content"', $html);
        // <html lang="…"> gesetzt.
        $this->assertMatchesRegularExpression('/<html[^>]+lang="/', $html);
        // Tabellenköpfe tragen scope="col" (über <x-table.th>).
        $this->assertStringContainsString('scope="col"', $html);
    }
}
