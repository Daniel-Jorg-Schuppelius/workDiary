<?php
/*
 * Created on   : Mon May 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : InvoiceMailTemplate.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Factories\{Factory, HasFactory};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Vom Admin editierbares Mail-Template für den Rechnungsversand.
 *
 * Templates können global (organization_id = null) oder pro Organisation
 * existieren. Body wird per einfachem {{var}}-Renderer aufgelöst — KEIN
 * Blade/Twig in DB-Inhalten, damit Template-Editoren keine Server-Code-Ausführung
 * triggern können.
 *
 * Verfügbare Variablen (siehe renderForInvoice()):
 *   {{customer_name}}, {{customer_email}}, {{invoice_number}},
 *   {{invoice_date}}, {{due_date}}, {{total}}, {{currency}},
 *   {{company_name}}, {{document_label}}
 *
 * @property int $id
 * @property int|null $organization_id
 * @property string $name
 * @property bool $is_default
 * @property string $subject
 * @property string $body_html
 * @property string $body_text
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class InvoiceMailTemplate extends Model {
    use BelongsToOrganization;

    /** @use HasFactory<Factory<static>> */
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'name',
        'is_default',
        'subject',
        'body_html',
        'body_text',
        'created_by',
        'updated_by',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'is_default' => 'boolean',
    ];

    /**
     * Default-Template für die Organisation (oder global). Stellt sicher,
     * dass immer ein Template existiert — fällt notfalls auf einen
     * hartkodierten Default zurück.
     */
    public static function defaultFor(?int $organizationId): self {
        $tpl = self::query()
            ->where('is_default', true)
            ->where(function ($q) use ($organizationId): void {
                $q->where('organization_id', $organizationId)->orWhereNull('organization_id');
            })
            ->orderByRaw('organization_id IS NULL') // Org-spezifisch bevorzugen
            ->first();

        return $tpl ?? self::makeFallback($organizationId);
    }

    /**
     * Liefert ein nicht-persistiertes Fallback-Template (sollte normalerweise
     * durch Seeder verhindert werden).
     */
    public static function makeFallback(?int $organizationId): self {
        return new self([
            'organization_id' => $organizationId,
            'name' => 'Fallback',
            'is_default' => true,
            'subject' => 'Ihre {{document_label}} {{invoice_number}}',
            'body_html' => "<p>Sehr geehrte Damen und Herren,</p>\n<p>anbei erhalten Sie unsere {{document_label}} {{invoice_number}} vom {{invoice_date}} über {{total}} {{currency}}.</p>\n<p>Mit freundlichen Grüßen<br>{{company_name}}</p>",
            'body_text' => "Sehr geehrte Damen und Herren,\n\nanbei erhalten Sie unsere {{document_label}} {{invoice_number}} vom {{invoice_date}} über {{total}} {{currency}}.\n\nMit freundlichen Grüßen\n{{company_name}}",
        ]);
    }

    /**
     * Rendert Subject + Body mit den Daten einer Invoice. Optional kann ein
     * Freitext-Begleittext (vor dem Body) eingefügt werden — über die
     * Variable {{custom_text}}.
     *
     * @return array{subject: string, html: string, text: string}
     */
    public function renderForInvoice(Invoice $invoice, ?string $customText = null): array {
        $vars = self::variablesFor($invoice, $customText);

        return [
            'subject' => self::replaceVars($this->subject, $vars, escape: false),
            'html' => self::replaceVars($this->body_html, $vars, escape: true),
            'text' => self::replaceVars($this->body_text, $vars, escape: false),
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function variablesFor(Invoice $invoice, ?string $customText = null): array {
        $invoice->loadMissing('customer');
        $companyName = (string) (config('branding.app_name') ?: config('app.name', 'workDiary'));

        return [
            'customer_name' => (string) ($invoice->customer->name ?? ''),
            'customer_email' => (string) ($invoice->customer->email ?? ''),
            'invoice_number' => (string) $invoice->number,
            'invoice_date' => optional($invoice->issued_on ?? $invoice->created_at)->format('d.m.Y') ?? '',
            'due_date' => optional($invoice->due_on)->format('d.m.Y') ?? '',
            'total' => number_format((float) $invoice->total, 2, ',', '.'),
            'currency' => (string) $invoice->currency,
            'company_name' => $companyName,
            'document_label' => $invoice->documentLabel(),
            'custom_text' => (string) ($customText ?? ''),
        ];
    }

    /**
     * Einfacher {{var}}-Renderer. HTML-Escaping verhindert XSS, wenn
     * Customer-Daten Sonderzeichen enthalten.
     *
     * @param  array<string, string>  $vars
     */
    private static function replaceVars(string $tpl, array $vars, bool $escape): string {
        return preg_replace_callback(
            '/\{\{\s*([a-z_]+)\s*\}\}/i',
            static function (array $m) use ($vars, $escape): string {
                $val = $vars[$m[1]] ?? '';

                return $escape ? htmlspecialchars($val, ENT_QUOTES | ENT_HTML5, 'UTF-8') : $val;
            },
            $tpl
        ) ?? $tpl;
    }

    /**
     * Liste aller verfügbaren Variablen (für die Admin-UI).
     *
     * @return array<string, string>
     */
    public static function availableVariables(): array {
        return [
            'customer_name' => __('Kunden-Name'),
            'customer_email' => __('Kunden-E-Mail'),
            'invoice_number' => __('Rechnungsnummer'),
            'invoice_date' => __('Rechnungsdatum'),
            'due_date' => __('Fälligkeitsdatum'),
            'total' => __('Gesamtbetrag'),
            'currency' => __('Währung'),
            'company_name' => __('Firmenname'),
            'document_label' => __('Dokumenttyp (Rechnung/Gutschrift)'),
            'custom_text' => __('Individueller Begleittext'),
        ];
    }
}
