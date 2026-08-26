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

use App\Enums\DocumentDesign\RenderDocumentKind;
use App\Models\Concerns\{BelongsToOrganization, HasSqid, Searchable};
use CommonToolkit\Helper\Data\NumberHelper;
use Illuminate\Database\Eloquent\{Builder, Model};
use Illuminate\Database\Eloquent\Factories\{Factory, HasFactory};
use Illuminate\Support\Carbon;

/**
 * Vom Admin editierbares Mail-Template für den Belegversand (Feature 128,
 * MVP-692: je Belegart via document_kind — Rechnung, Angebot, AB,
 * Bestellung, Lieferschein).
 *
 * Templates können global (organization_id = null) oder pro Organisation
 * existieren. Body wird per einfachem {{var}}-Renderer aufgelöst — KEIN
 * Blade/Twig in DB-Inhalten, damit Template-Editoren keine Server-Code-Ausführung
 * triggern können. Verfügbare Variablen je Art: {@see availableVariables()}.
 *
 * @property int $id
 * @property int|null $organization_id
 * @property string $name
 * @property string $document_kind
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
    use HasSqid;
    use Searchable;

    protected $fillable = [
        'organization_id',
        'name',
        'document_kind',
        'is_default',
        'subject',
        'body_html',
        'body_text',
        'created_by',
        'updated_by',
    ];

    /** @var array<string, mixed> */
    protected $attributes = [
        'document_kind' => 'invoice',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'is_default' => 'boolean',
    ];

    /**
     * Belegarten mit Mail-Vorlagen: alles, was einen eigenen PDF-Renderer
     * und einen Versandweg hat (Feature 128). Mahnungen bleiben bewusst
     * draußen — deren Mail baut der Mahnlauf (MVP-691) selbst.
     *
     * @return list<RenderDocumentKind>
     */
    public static function supportedKinds(): array {
        return [
            RenderDocumentKind::Invoice,
            RenderDocumentKind::Quote,
            RenderDocumentKind::OrderConfirmation,
            RenderDocumentKind::PurchaseOrder,
            RenderDocumentKind::DeliveryNote,
            // VOB/B-Schreiben (Feature 062, MVP-728).
            RenderDocumentKind::ConstructionObstructionNotice,
            RenderDocumentKind::ConstructionConcernNotice,
        ];
    }

    /**
     * Templates der Belegart, org-eigene und globale (Default zuerst).
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeForKind(Builder $query, RenderDocumentKind $kind): Builder {
        return $query->where('document_kind', $kind->value);
    }

    /**
     * Default-Template für Organisation und Belegart. Stellt sicher, dass
     * immer ein Template existiert — fällt notfalls auf einen hartkodierten
     * Default zurück.
     */
    public static function defaultFor(?int $organizationId, RenderDocumentKind $kind = RenderDocumentKind::Invoice): self {
        $tpl = self::query()
            ->where('document_kind', $kind->value)
            ->where('is_default', true)
            ->where(function ($q) use ($organizationId): void {
                $q->where('organization_id', $organizationId)->orWhereNull('organization_id');
            })
            ->orderByRaw('organization_id IS NULL') // Org-spezifisch bevorzugen
            ->first();

        return $tpl ?? self::makeFallback($organizationId, $kind);
    }

    /**
     * Liefert ein nicht-persistiertes Fallback-Template (sollte normalerweise
     * durch Seeder verhindert werden). Rechnungen behalten ihren Wortlaut
     * (Betrag/Fälligkeit); andere Belegarten bekommen den neutralen Text.
     */
    public static function makeFallback(?int $organizationId, RenderDocumentKind $kind = RenderDocumentKind::Invoice): self {
        if ($kind === RenderDocumentKind::Invoice) {
            return new self([
                'organization_id' => $organizationId,
                'name' => 'Fallback',
                'document_kind' => $kind->value,
                'is_default' => true,
                'subject' => 'Ihre {{document_label}} {{invoice_number}}',
                'body_html' => "<p>Sehr geehrte Damen und Herren,</p>\n<p>anbei erhalten Sie unsere {{document_label}} {{invoice_number}} vom {{invoice_date}} über {{total}} {{currency}}.</p>\n<p>Mit freundlichen Grüßen<br>{{company_name}}</p>",
                'body_text' => "Sehr geehrte Damen und Herren,\n\nanbei erhalten Sie unsere {{document_label}} {{invoice_number}} vom {{invoice_date}} über {{total}} {{currency}}.\n\nMit freundlichen Grüßen\n{{company_name}}",
            ]);
        }

        if ($kind === RenderDocumentKind::ConstructionObstructionNotice || $kind === RenderDocumentKind::ConstructionConcernNotice) {
            // Foermliches Schreiben: der Betreff traegt die Sache, der Body
            // verweist auf das PDF — Rechtsverweis steht im Dokument.
            return new self([
                'organization_id' => $organizationId,
                'name' => 'Fallback',
                'document_kind' => $kind->value,
                'is_default' => true,
                'subject' => '{{document_label}} {{document_number}}: {{document_subject}}',
                'body_html' => "<p>Sehr geehrte Damen und Herren,</p>\n<p>{{custom_text}}</p>\n<p>anbei erhalten Sie unsere {{document_label}} {{document_number}} vom {{document_date}} zum Vorgang „{{document_subject}}\" ({{project_name}}).</p>\n<p>Mit freundlichen Grüßen<br>{{company_name}}</p>",
                'body_text' => "Sehr geehrte Damen und Herren,\n\n{{custom_text}}\n\nanbei erhalten Sie unsere {{document_label}} {{document_number}} vom {{document_date}} zum Vorgang \"{{document_subject}}\" ({{project_name}}).\n\nMit freundlichen Grüßen\n{{company_name}}",
            ]);
        }

        return new self([
            'organization_id' => $organizationId,
            'name' => 'Fallback',
            'document_kind' => $kind->value,
            'is_default' => true,
            'subject' => 'Ihre {{document_label}} {{document_number}}',
            'body_html' => "<p>Sehr geehrte Damen und Herren,</p>\n<p>{{custom_text}}</p>\n<p>anbei erhalten Sie unsere {{document_label}} {{document_number}} vom {{document_date}}.</p>\n<p>Mit freundlichen Grüßen<br>{{company_name}}</p>",
            'body_text' => "Sehr geehrte Damen und Herren,\n\n{{custom_text}}\n\nanbei erhalten Sie unsere {{document_label}} {{document_number}} vom {{document_date}}.\n\nMit freundlichen Grüßen\n{{company_name}}",
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
        return $this->render(self::variablesFor($invoice, $customText));
    }

    /**
     * Rendert Subject + Body mit einem fertigen Variablensatz (generischer
     * Belegversand — die Variablen baut {@see \App\Services\Document\DocumentMailService}).
     *
     * @param  array<string, string>  $vars
     * @return array{subject: string, html: string, text: string}
     */
    public function render(array $vars): array {
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
            // Aliasse des generischen Belegversands — Vorlagen können beide Schreibweisen nutzen.
            'document_number' => (string) $invoice->number,
            'document_date' => optional($invoice->issued_on ?? $invoice->created_at)->format('d.m.Y') ?? '',
            'due_date' => optional($invoice->due_on)->format('d.m.Y') ?? '',
            'total' => NumberHelper::toGermanFormat($invoice->total?->toFloat() ?? 0.0, 2, withThousandsSeparator: true),
            'currency' => $invoice->currency->value,
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
     * Liste aller verfügbaren Variablen der Belegart (für die Admin-UI und
     * den Versanddialog).
     *
     * @return array<string, string>
     */
    public static function availableVariables(RenderDocumentKind $kind = RenderDocumentKind::Invoice): array {
        $common = [
            'document_number' => __('Belegnummer'),
            'document_date' => __('Belegdatum'),
            'document_label' => __('Belegart-Bezeichnung'),
            'company_name' => __('Firmenname'),
            'custom_text' => __('Individueller Begleittext'),
        ];

        return match ($kind) {
            RenderDocumentKind::Invoice => [
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
            ],
            RenderDocumentKind::Quote, RenderDocumentKind::OrderConfirmation => $common + [
                'customer_name' => __('Kunden-Name'),
                'customer_email' => __('Kunden-E-Mail'),
                'valid_until' => __('Bindefrist'),
                'total' => __('Gesamtbetrag'),
                'currency' => __('Währung'),
            ],
            RenderDocumentKind::PurchaseOrder => $common + [
                'supplier_name' => __('Lieferanten-Name'),
                'supplier_email' => __('Lieferanten-E-Mail'),
                'currency' => __('Währung'),
            ],
            RenderDocumentKind::DeliveryNote => $common + [
                'customer_name' => __('Kunden-Name'),
                'customer_email' => __('Kunden-E-Mail'),
            ],
            RenderDocumentKind::ConstructionObstructionNotice,
            RenderDocumentKind::ConstructionConcernNotice => $common + [
                'customer_name' => __('Empfänger'),
                'customer_email' => __('Empfänger-E-Mail'),
                'document_subject' => __('Betreff des Schreibens'),
                'project_name' => __('Bauvorhaben / Einsatzort'),
                'legal_reference' => __('Rechtsverweis'),
            ],
            default => $common,
        };
    }

    /** @return list<string> */
    protected function searchableColumns(): array {
        return ['name', 'subject'];
    }
}
