<?php
/*
 * Created on   : Mon May 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : InvoiceMailTemplateSeeder.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Database\Seeders;

use App\Models\InvoiceMailTemplate;
use Illuminate\Database\Seeder;

/**
 * Legt ein globales Default-Template für den Rechnungsversand an,
 * wenn noch keines existiert.
 */
class InvoiceMailTemplateSeeder extends Seeder {
    public function run(): void {
        if (InvoiceMailTemplate::query()->where('is_default', true)->exists()) {
            return;
        }

        InvoiceMailTemplate::create([
            'organization_id' => null,
            'name' => 'Standard (Deutsch)',
            'is_default' => true,
            'subject' => 'Ihre {{document_label}} {{invoice_number}} vom {{invoice_date}}',
            'body_html' => <<<'HTML'
<p>Sehr geehrte Damen und Herren,</p>

<p>anbei erhalten Sie unsere {{document_label}} <strong>{{invoice_number}}</strong> vom {{invoice_date}}
über <strong>{{total}} {{currency}}</strong>.</p>

<p>Bitte überweisen Sie den Betrag bis zum <strong>{{due_date}}</strong>.</p>

{{custom_text}}

<p>Mit freundlichen Grüßen<br>
{{company_name}}</p>
HTML,
            'body_text' => <<<'TEXT'
Sehr geehrte Damen und Herren,

anbei erhalten Sie unsere {{document_label}} {{invoice_number}} vom {{invoice_date}}
über {{total}} {{currency}}.

Bitte überweisen Sie den Betrag bis zum {{due_date}}.

{{custom_text}}

Mit freundlichen Grüßen
{{company_name}}
TEXT,
        ]);
    }
}
