<?php
/*
 * Created on   : Fri Jul 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DesignContext.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\DocumentDesign;

use App\Enums\DocumentDesign\{InformationBlock, InformationBlockState};

/**
 * Typisierter Rendervertrag für die PDF-Views (MVP-295): stellt Blockzustände
 * und Positionierungshilfen bereit. Views entscheiden nur über Sichtbarkeit —
 * fachliche Werte berechnet weiterhin das Fachmodul. Ohne Profil verhält sich
 * der Kontext wie der Systemfallback (alles dynamisch, keine Fenster).
 */
class DesignContext {
    /** @param array<string, mixed>|null $payload Eingefrorenes oder aktives Profil-Payload. */
    public function __construct(public readonly ?array $payload = null) {}

    public function hasProfile(): bool {
        return $this->payload !== null;
    }

    /**
     * Seitenrand-Defaults (mm) als Single-Source für Renderer UND Preflight
     * (Vollaudit 2026-07, N35) — Drift zwischen Geometrieprüfung und
     * @page-CSS wäre ein stiller Layoutfehler.
     *
     * @param  array<string, mixed>  $m
     * @return array{top: float, right: float, bottom: float, left: float}
     */
    public static function margins(array $m): array {
        return [
            'top' => (float) ($m['top'] ?? 20),
            'right' => (float) ($m['right'] ?? 20),
            'bottom' => (float) ($m['bottom'] ?? 20),
            'left' => (float) ($m['left'] ?? 20),
        ];
    }

    /** Sichtbarkeit eines Blocks: nur `dynamic` wird von WorkDiary gedruckt. */
    public function show(InformationBlock $block): bool {
        if ($this->payload === null) {
            return $block->defaultState() === InformationBlockState::Dynamic;
        }
        $raw = $this->payload['block_rules'][$block->value]['state'] ?? null;
        $state = InformationBlockState::tryFrom((string) $raw) ?? $block->defaultState();

        return $state === InformationBlockState::Dynamic;
    }

    /**
     * Inline-Style für das Empfängerfenster (absolut in mm, relativ zum
     * Inhaltsbereich der ersten Seite). Null, wenn kein Fenster definiert ist —
     * die View rendert die Anschrift dann im normalen Fluss.
     */
    public function addressWindowStyle(): ?string {
        $box = $this->payload['layout']['address_window'] ?? null;
        if (! is_array($box)) {
            return null;
        }
        $margins = (array) ($this->payload['layout']['content_first'] ?? []);

        return sprintf(
            'position: absolute; left: %.1fmm; top: %.1fmm; width: %.1fmm; height: %.1fmm; overflow: hidden;',
            (float) $box['x'] - (float) ($margins['left'] ?? 20),
            (float) $box['y'] - (float) ($margins['top'] ?? 20),
            (float) $box['width'],
            (float) $box['height'],
        );
    }

    /** Inline-Style für die Absenderzeile oberhalb des Adressfensters. */
    public function senderLineStyle(): ?string {
        $box = $this->payload['layout']['sender_line'] ?? null;
        if (! is_array($box)) {
            return null;
        }
        $margins = (array) ($this->payload['layout']['content_first'] ?? []);

        return sprintf(
            'position: absolute; left: %.1fmm; top: %.1fmm; width: %.1fmm; font-size: 7px; text-decoration: underline; overflow: hidden;',
            (float) $box['x'] - (float) ($margins['left'] ?? 20),
            (float) $box['y'] - (float) ($margins['top'] ?? 20),
            (float) $box['width'],
        );
    }
}
