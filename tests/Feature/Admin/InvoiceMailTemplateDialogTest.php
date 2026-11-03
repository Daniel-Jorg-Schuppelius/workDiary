<?php
/*
 * Created on   : Tue Nov 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : InvoiceMailTemplateDialogTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\Invoicing\InvoiceMailTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/** Rechnungs-Mailvorlagen: Anlegen und Bearbeiten sind Dialog-Fragmente (Dialog-Standard, 2026-11-03). */
class InvoiceMailTemplateDialogTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    public function test_create_and_edit_render_the_dialog_fragment(): void {
        $this->setUpOrganization();
        $admin = $this->orgAdmin();

        $this->actingAs($admin)->get(route('admin.invoice-mail-templates.create'))
            ->assertOk()->assertViewIs('admin.invoice-mail-templates._form_dialog')->assertSee('data-entry-form', false);

        $template = InvoiceMailTemplate::query()->create([
            'organization_id' => $this->organization->id,
            'name' => 'Rechnung Standard',
            'document_kind' => InvoiceMailTemplate::supportedKinds()[0]->value,
            'is_default' => true,
            'subject' => 'Ihre Rechnung {{invoice_number}}',
            'body_html' => '<p>Hallo</p>',
            'body_text' => 'Hallo',
        ]);

        $this->actingAs($admin)->get(route('admin.invoice-mail-templates.edit', $template))
            ->assertOk()->assertViewIs('admin.invoice-mail-templates._form_dialog')->assertSee('Rechnung Standard');
    }
}
