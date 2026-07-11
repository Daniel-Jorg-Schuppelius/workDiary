<?php
/*
 * Created on   : Fri Jul 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RenderPreflightService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\DocumentDesign;

use App\Enums\DocumentDesign\{InformationBlock, InformationBlockState, RenderDocumentKind, TableStylePreset};
use App\Models\DocumentDesign\DocumentRenderProfileVersion;
use CommonToolkit\Helper\Data\ColorHelper;

/**
 * Preflight einer Profilversion (MVP-297/298/299): Layoutgeometrie,
 * Pflichtblöcke je Dokumentart und Tabellenkontrast. Fehler verhindern die
 * Aktivierung — es entsteht kein still unvollständiges Dokument.
 */
class RenderPreflightService {
    private const PAGE_W = 210.0;

    private const PAGE_H = 297.0;

    /** @param array<int, string>|null $kinds */
    public function check(DocumentRenderProfileVersion $version, ?array $kinds = null): PreflightResult {
        $result = new PreflightResult();

        $this->checkLayout($version->layout, $result);
        $this->checkAssets($version, $result);
        $this->checkBlocks($version, $kinds ?? $version->profile->document_kinds ?? [], $result);
        $this->checkTableStyle($version->table_style, $result);

        return $result;
    }

    /** @param array<string, mixed> $layout */
    private function checkLayout(array $layout, PreflightResult $result): void {
        $minEdge = (float) ($layout['page']['min_edge_mm'] ?? 5.0);
        $minW = (float) config('document_design.min_content_mm.width');
        $minH = (float) config('document_design.min_content_mm.height');

        $first = $this->margins($layout['content_first'] ?? []);
        $following = $this->margins($layout['content_following'] ?? []);

        foreach (['first' => $first, 'following' => $following] as $page => $m) {
            $label = $page === 'first' ? __('Erste Seite') : __('Folgeseiten');
            if (min($m['top'], $m['right'], $m['bottom'], $m['left']) < $minEdge) {
                $result->error('min_edge', __(':page: Der Mindestabstand zum Papierrand (:mm mm) ist unterschritten.', ['page' => $label, 'mm' => $minEdge]), $page);
            }
            $w = self::PAGE_W - $m['left'] - $m['right'];
            $h = self::PAGE_H - $m['top'] - $m['bottom'];
            if ($w < $minW || $h < $minH) {
                $result->error('content_too_small', __(':page: Der Inhaltsbereich ist zu klein (mindestens :w × :h mm).', ['page' => $label, 'w' => $minW, 'h' => $minH]), $page);
            }
        }

        // Renderer-Vertrag des MVP: gleiche Seitenbreite auf allen Seiten,
        // erste Seite darf nur später beginnen (größerer Kopfbereich).
        if (abs($first['left'] - $following['left']) > 0.5 || abs($first['right'] - $following['right']) > 0.5) {
            $result->error('horizontal_mismatch', __('Linker und rechter Inhaltsrand müssen auf erster und Folgeseite übereinstimmen (MVP-Renderer).'));
        }
        if ($first['top'] < $following['top'] - 0.5) {
            $result->error('first_top_smaller', __('Der Inhaltsbereich der ersten Seite darf nicht oberhalb des Folgeseiten-Bereichs beginnen (MVP-Renderer).'), 'first');
        }
        if (abs($first['bottom'] - $following['bottom']) > 0.5) {
            $result->warn('bottom_mismatch', __('Unterschiedliche untere Ränder: der größere Wert gilt für alle Seiten.'));
        }

        // Adressfenster und Absenderzeile müssen auf der Seite liegen.
        foreach ([['address_window', __('Empfängerfenster')], ['sender_line', __('Absenderzeile')]] as [$key, $label]) {
            $box = $layout[$key] ?? null;
            if (! is_array($box)) {
                continue;
            }
            $x = (float) ($box['x'] ?? 0);
            $y = (float) ($box['y'] ?? 0);
            $w = (float) ($box['width'] ?? 0);
            $h = (float) ($box['height'] ?? 8.0);
            if ($x < 0 || $y < 0 || $x + $w > self::PAGE_W || $y + $h > self::PAGE_H || $w <= 0) {
                $result->error('box_off_page', __(':box liegt außerhalb der Seite.', ['box' => $label]), 'first');
            }
        }

        // Sperrflächen dürfen den Inhaltsbereich ihrer Seite nicht schneiden —
        // so laufen Tabellen und Fließtext konstruktionsbedingt nie hinein.
        foreach ((array) ($layout['blocked_areas'] ?? []) as $i => $area) {
            if (! is_array($area)) {
                continue;
            }
            $pages = ($area['page'] ?? 'all') === 'all' ? ['first', 'following'] : [(string) $area['page']];
            foreach ($pages as $page) {
                $m = $page === 'first' ? $first : $following;
                $content = ['x' => $m['left'], 'y' => $m['top'], 'width' => self::PAGE_W - $m['left'] - $m['right'], 'height' => self::PAGE_H - $m['top'] - $m['bottom']];
                if ($this->intersects($area, $content)) {
                    $result->error('blocked_overlap', __('Sperrfläche :n überschneidet den Inhaltsbereich (:page). Inhaltsbereich verkleinern oder Sperrfläche verschieben.', [
                        'n' => $i + 1,
                        'page' => $page === 'first' ? __('Erste Seite') : __('Folgeseiten'),
                    ]), $page);
                }
            }
        }
    }

    private function checkAssets(DocumentRenderProfileVersion $version, PreflightResult $result): void {
        foreach ([['firstAsset', 'first'], ['followingAsset', 'following']] as [$relation, $page]) {
            $asset = $version->{$relation};
            if ($asset !== null && ! $asset->isReady()) {
                $result->error('asset_not_ready', __('Der zugewiesene Firmenbogen (:page) ist nicht einsatzbereit.', [
                    'page' => $page === 'first' ? __('Erste Seite') : __('Folgeseiten'),
                ]), $page);
            }
        }
    }

    /** @param array<int, string> $kinds */
    private function checkBlocks(DocumentRenderProfileVersion $version, array $kinds, PreflightResult $result): void {
        $hasLetterhead = $version->first_asset_id !== null;

        foreach (InformationBlock::cases() as $block) {
            $state = $version->blockState($block);
            if ($state !== InformationBlockState::ProvidedByLetterhead) {
                continue;
            }
            if ($block->dynamicOnly()) {
                $result->error('dynamic_only', __('„:block" enthält veränderliche Belegdaten und kann nicht vom Firmenbogen bereitgestellt werden.', ['block' => $block->label()]), null, $block->value);
            }
            if (! $hasLetterhead) {
                $result->error('no_letterhead', __('„:block" ist als „bereits auf dem Firmenbogen" deklariert, aber es ist kein Firmenbogen zugewiesen.', ['block' => $block->label()]), null, $block->value);
            }
            if (empty($version->block_rules[$block->value]['confirmed'])) {
                $result->error('unconfirmed', __('„:block": Die Abdeckung durch den Firmenbogen ist nicht bestätigt.', ['block' => $block->label()]), null, $block->value);
            }
        }

        foreach ($kinds as $kindValue) {
            $kind = RenderDocumentKind::tryFrom((string) $kindValue);
            if ($kind === null) {
                continue;
            }
            foreach ($kind->mandatoryBlocks() as $block) {
                if ($version->blockState($block) === InformationBlockState::NotApplicable) {
                    $result->error('mandatory_missing', __(':kind: Pflichtblock „:block" darf nicht „nicht anwendbar" sein.', [
                        'kind' => $kind->label(),
                        'block' => $block->label(),
                    ]), null, $block->value);
                }
            }
        }
    }

    /** @param array<string, mixed> $tableStyle */
    private function checkTableStyle(array $tableStyle, PreflightResult $result): void {
        $preset = TableStylePreset::tryFrom((string) ($tableStyle['preset'] ?? '')) ?? TableStylePreset::Clear;
        $style = array_merge($preset->settings(), (array) ($tableStyle['overrides'] ?? []));
        $min = (float) config('document_design.min_contrast');

        $pairs = [
            [__('Tabellentext auf Papier'), (string) $style['text_color'], '#ffffff'],
            [__('Kopfzeilentext auf Kopfzeilenfläche'), (string) $style['header_text_color'], (string) $style['header_fill']],
        ];
        if (! empty($style['zebra'])) {
            $pairs[] = [__('Tabellentext auf Zebrazeile'), (string) $style['text_color'], (string) $style['zebra_fill']];
        }

        foreach ($pairs as [$label, $fg, $bg]) {
            if (ColorHelper::contrastRatio($fg, $bg) < $min) {
                $result->error('contrast', __('Unzureichender Kontrast: :pair (mindestens :min:1).', ['pair' => $label, 'min' => $min]));
            }
        }
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

    /**
     * @param  array<string, mixed>  $a
     * @param  array{x: float, y: float, width: float, height: float}  $b
     */
    private function intersects(array $a, array $b): bool {
        $ax = (float) ($a['x'] ?? 0);
        $ay = (float) ($a['y'] ?? 0);
        $aw = (float) ($a['width'] ?? 0);
        $ah = (float) ($a['height'] ?? 0);

        return $ax < (float) $b['x'] + (float) $b['width']
            && $ax + $aw > (float) $b['x']
            && $ay < (float) $b['y'] + (float) $b['height']
            && $ay + $ah > (float) $b['y'];
    }
}
