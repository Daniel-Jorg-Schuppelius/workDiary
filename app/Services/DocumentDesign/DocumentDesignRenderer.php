<?php
/*
 * Created on   : Fri Jul 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DocumentDesignRenderer.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\DocumentDesign;

use App\Enums\DocumentDesign\{RenderDocumentKind, TableStylePreset};
use App\Models\DocumentDesign\{DocumentRenderProfileVersion, DocumentRenderSnapshot};
use App\Models\{Organization, User};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\View;
use PDFToolkit\Entities\PDFContent;
use PDFToolkit\Registries\PDFWriterRegistry;
use RuntimeException;

/**
 * Gemeinsame Renderpipeline (MVP-301): komponiert Firmenbogen (erste Seite /
 * Folgeseiten), Druckbereiche, Seitenzahlen und Tabellenstil in das vom
 * Fachmodul gelieferte Dokument-HTML — vor der PDF-Erzeugung, damit der
 * ZUGFeRD-/PDF-A-3-Pfad (dompdf → FPDI → XML-Einbettung) unverändert valide
 * bleibt. Ohne Profil ist die Komposition ein No-Op (Systemfallback =
 * heutige Ausgabe). Finalisierte Dokumente rendern ausschließlich über ihren
 * eingefrorenen Snapshot.
 */
class DocumentDesignRenderer {
    public const GENERATOR_VERSION = 'document-design/1.0.0';

    public function __construct(private readonly RenderProfileService $profiles) {}

    /**
     * Payload des für Org+Dokumentart wirksamen Profils (aktive Version) oder
     * null (Systemfallback). Snapshots liefern ihr eingefrorenes Payload
     * direkt an compose()/context().
     *
     * @return array<string, mixed>|null
     */
    public function payloadFor(Organization $organization, RenderDocumentKind $kind): ?array {
        $version = $this->profiles->resolveFor($organization, $kind);

        return $version === null ? null : $this->payloadFromVersion($version);
    }

    /** @return array<string, mixed> */
    public function payloadFromVersion(DocumentRenderProfileVersion $version): array {
        return [
            'profile_id' => $version->document_render_profile_id,
            'profile_version_id' => $version->id,
            'profile_version' => $version->version,
            'layout' => $version->layout,
            'block_rules' => $version->block_rules,
            'table_style' => $version->table_style,
            'assets' => [
                'first' => $version->firstAsset()->withoutGlobalScopes()->first()?->normalizedDataUri(),
                'following' => $version->followingAsset()->withoutGlobalScopes()->first()?->normalizedDataUri(),
                'first_sha256' => $version->firstAsset()->withoutGlobalScopes()->first()?->normalized_sha256,
                'following_sha256' => $version->followingAsset()->withoutGlobalScopes()->first()?->normalized_sha256,
            ],
            'generator_version' => self::GENERATOR_VERSION,
        ];
    }

    /** @param array<string, mixed>|null $payload */
    public function context(?array $payload): DesignContext {
        return new DesignContext($payload);
    }

    /**
     * Bequemer Einzeiler für Fachmodule ohne Snapshot-Pflicht (MVP-303):
     * komponiert das Dokument-HTML mit dem aktiven Profil der Organisation —
     * ohne Profil unverändert (kontrolliertes Standardprofil).
     */
    public function composeFor(?Organization $organization, RenderDocumentKind $kind, string $html): string {
        if ($organization === null) {
            return $html;
        }

        return $this->compose($html, $this->payloadFor($organization, $kind));
    }

    /**
     * Kompletter View→Design→PDF-Dreischritt der Fachmodule (C15): rendert die
     * Blade-View, komponiert das aktive Dokumentdesign (ohne Profil No-Op) und
     * erzeugt das PDF über die pdf-toolkit Registry. Organisation wahlweise als
     * Instanz oder per ID (Lookup ohne globale Scopes).
     *
     * @param array<string, mixed> $data
     */
    public function renderPdf(RenderDocumentKind $kind, string $view, array $data, int|Organization|null $organization): string {
        if (is_int($organization)) {
            $organization = Organization::query()->withoutGlobalScopes()->find($organization);
        }

        $html = View::make($view, $data)->render();
        $html = $this->composeFor($organization, $kind, $html);

        return PDFWriterRegistry::getInstance()->createPdfString(PDFContent::fromHtml($html))
            ?? throw new RuntimeException('PDF-Erzeugung fehlgeschlagen (' . $view . ').');
    }

    /**
     * Komposition: injiziert Profil-CSS (Druckbereiche, Tabellenstil) und die
     * Firmenbogen-Hintergründe in ein vollständiges Dokument-HTML. Die
     * Reihenfolge ist definiert: Hintergrund → dynamische Blöcke → Tabellen;
     * es wird nichts nachträglich auf ein fertiges PDF gestempelt.
     *
     * @param array<string, mixed>|null $payload
     */
    public function compose(string $html, ?array $payload): string {
        if ($payload === null) {
            return $html;
        }

        $layout = (array) ($payload['layout'] ?? []);
        $first = $this->margins((array) ($layout['content_first'] ?? []));
        $following = $this->margins((array) ($layout['content_following'] ?? []));
        // MVP-Renderer: einheitliche @page-Ränder = Folgeseiten, unten der
        // größere Wert beider Seiten (Preflight erzwingt passende Geometrie).
        $bottom = max($first['bottom'], $following['bottom']);

        $css = sprintf(
            "@page { margin: %.1fmm %.1fmm %.1fmm %.1fmm; }\n",
            $following['top'],
            $following['right'],
            $bottom,
            $following['left'],
        );
        $css .= $this->tableCss((array) ($payload['table_style'] ?? []));

        $body = '';
        $assets = (array) ($payload['assets'] ?? []);
        // dompdf positioniert fixe/absolute Elemente relativ zum Randkasten —
        // negative Offsets in Randbreite lassen den Bogen die volle Seite decken.
        if (! empty($assets['following'])) {
            $css .= sprintf(
                ".dd-lh-following { position: fixed; top: %.1fmm; left: %.1fmm; width: 210mm; height: 297mm; z-index: -1000; }\n",
                -$following['top'],
                -$following['left'],
            );
            $body .= '<img class="dd-lh-following" src="' . $assets['following'] . '" alt="">';
        }
        if (! empty($assets['first'])) {
            $css .= sprintf(
                ".dd-lh-first { position: absolute; top: %.1fmm; left: %.1fmm; width: 210mm; height: 297mm; z-index: -999; }\n",
                -$following['top'],
                -$following['left'],
            );
            $body .= '<img class="dd-lh-first" src="' . $assets['first'] . '" alt="">';
        }

        // Erste Seite beginnt tiefer als Folgeseiten → Abstandshalter.
        $delta = $first['top'] - $following['top'];
        if ($delta > 0.05) {
            $body .= sprintf('<div class="dd-first-offset" style="height: %.1fmm"></div>', $delta);
        }

        if (! empty($layout['footer']['page_numbers'])) {
            $css .= sprintf(
                ".dd-pagenum { position: fixed; bottom: %.1fmm; right: 0; font-size: 8px; color: #555; }\n"
                . ".dd-pagenum:after { content: counter(page); }\n",
                -($bottom - 6),
            );
            $body .= '<div class="dd-pagenum">' . __('Seite') . ' </div>';
        }

        $html = $this->injectHead($html, '<style>' . $css . '</style>');

        return preg_replace('/<body([^>]*)>/', '<body$1>' . str_replace('\\', '\\\\', $body), $html, 1)
            ?? $html;
    }

    /**
     * Snapshot beim Finalisieren (MVP-300): friert Profilversion, Layout,
     * Blockregeln, Tabellenstil, Asset-Hashes und Generatorversion ein.
     * Existiert bereits ein Snapshot, bleibt er unverändert (idempotent).
     *
     * @param array<string, mixed>|null $payload
     */
    public function snapshot(Model $documentable, RenderDocumentKind $kind, Organization $organization, ?array $payload = null, ?User $user = null): DocumentRenderSnapshot {
        $existing = DocumentRenderSnapshot::query()
            ->withoutGlobalScopes()
            ->where('documentable_type', $documentable->getMorphClass())
            ->where('documentable_id', $documentable->getKey())
            ->where('document_kind', $kind->value)
            ->first();
        if ($existing !== null) {
            return $existing;
        }

        $payload ??= $this->payloadFor($organization, $kind);
        $stored = $payload ?? ['fallback' => 'system_default', 'generator_version' => self::GENERATOR_VERSION];
        // Data-URIs nicht im Snapshot persistieren — die Assets sind über
        // ihre Hashes nachweisbar und bleiben in der Ablage erhalten.
        $assets = (array) ($stored['assets'] ?? []);
        unset($stored['assets']['first'], $stored['assets']['following']);

        return DocumentRenderSnapshot::create([
            'organization_id' => $organization->id,
            'document_render_profile_id' => $payload['profile_id'] ?? null,
            'profile_version_id' => $payload['profile_version_id'] ?? null,
            'document_kind' => $kind->value,
            'documentable_type' => $documentable->getMorphClass(),
            'documentable_id' => $documentable->getKey(),
            'payload' => $stored,
            'first_asset_sha256' => $assets['first_sha256'] ?? null,
            'following_asset_sha256' => $assets['following_sha256'] ?? null,
            'generator_version' => self::GENERATOR_VERSION,
            'created_by' => $user?->id,
        ]);
    }

    /**
     * Render-Payload für ein finalisiertes Dokument: eingefrorener Snapshot
     * (mit nachgeladenen Asset-Data-URIs) oder null, wenn keiner existiert.
     *
     * @return array<string, mixed>|null
     */
    public function payloadFromSnapshot(Model $documentable, RenderDocumentKind $kind): ?array {
        $snapshot = DocumentRenderSnapshot::query()
            ->withoutGlobalScopes()
            ->where('documentable_type', $documentable->getMorphClass())
            ->where('documentable_id', $documentable->getKey())
            ->where('document_kind', $kind->value)
            ->first();
        if ($snapshot === null) {
            return null;
        }

        $payload = $snapshot->payload;
        if (isset($payload['fallback'])) {
            return null; // Snapshot des Systemfallbacks → heutige Ausgabe.
        }

        // Assets über die eingefrorene Profilversion nachladen; die Hashes im
        // Snapshot belegen, dass derselbe Stand gerendert wird.
        if ($snapshot->profile_version_id !== null) {
            $version = DocumentRenderProfileVersion::query()->withoutGlobalScopes()->find($snapshot->profile_version_id);
            if ($version !== null) {
                $payload['assets']['first'] = $version->firstAsset()->withoutGlobalScopes()->first()?->normalizedDataUri();
                $payload['assets']['following'] = $version->followingAsset()->withoutGlobalScopes()->first()?->normalizedDataUri();
            }
        }

        return $payload;
    }

    /** @param array<string, mixed> $tableStyle */
    private function tableCss(array $tableStyle): string {
        $preset = TableStylePreset::tryFrom((string) ($tableStyle['preset'] ?? '')) ?? TableStylePreset::Clear;
        $s = array_merge($preset->settings(), (array) ($tableStyle['overrides'] ?? []));

        $css = sprintf(
            "table { font-family: '%s', sans-serif; font-size: %dpx; line-height: %.2f; }\n",
            $s['font_family'],
            (int) $s['font_size'],
            (float) $s['line_height'],
        );
        $css .= sprintf(
            "th { background: %s; color: %s; }\n",
            $s['header_fill'],
            $s['header_text_color'],
        );
        $css .= sprintf(
            "th, td { padding: %dpx %dpx; color: %s; ",
            (int) $s['cell_padding_v'],
            (int) $s['cell_padding_h'],
            $s['text_color'],
        );
        $css .= match ($s['grid']) {
            'full' => 'border: 0.5px solid #bbb; ',
            'minimal' => 'border: none; ',
            default => 'border-bottom: 1px solid #ccc; border-left: none; border-right: none; ',
        };
        $css .= "}\n";
        if ($s['grid'] === 'minimal') {
            $css .= sprintf("th { border-bottom: 1px solid %s; }\n", $s['accent_color']);
        }
        if (! empty($s['zebra'])) {
            $css .= sprintf("tbody tr:nth-child(even) td { background: %s; }\n", $s['zebra_fill']);
        }
        if (! empty($s['repeat_header'])) {
            $css .= "thead { display: table-header-group; }\n";
        }
        if (! empty($s['highlight_totals'])) {
            $css .= sprintf("tfoot td { border-top: 2px solid %s; font-weight: bold; }\n", $s['accent_color']);
        }

        return $css;
    }

    /**
     * @param  array<string, mixed>  $m
     * @return array{top: float, right: float, bottom: float, left: float}
     */
    private function margins(array $m): array {
        return [
            'top' => (float) ($m['top'] ?? 20),
            'right' => (float) ($m['right'] ?? 20),
            'bottom' => (float) ($m['bottom'] ?? 20),
            'left' => (float) ($m['left'] ?? 20),
        ];
    }

    private function injectHead(string $html, string $inject): string {
        if (str_contains($html, '</head>')) {
            return str_replace('</head>', $inject . '</head>', $html);
        }
        if (str_contains($html, '<body')) {
            return (string) preg_replace('/<body/', $inject . '<body', $html, 1);
        }

        return $inject . $html;
    }
}
