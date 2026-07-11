<?php
/*
 * Created on   : Fri Jul 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : document_design.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

/*
 * Phase 28 (Feature 076): PDF-Dokumentdesign und Firmenbogen.
 * Uploads werden größen- und pixelbegrenzt; PDF-Firmenbögen werden auf eine
 * nicht interaktive Rasterrepräsentation (PNG) reduziert.
 */
return [
    // Ablage der Original- und normalisierten Firmenbogen-Dateien.
    'disk' => env('DOCUMENT_DESIGN_DISK', 'local'),

    'limits' => [
        // Maximale Uploadgröße in KB (PDF/JPG/PNG).
        'max_kb' => (int) env('DOCUMENT_DESIGN_MAX_KB', 10240),
        // Pixel-Obergrenze (Breite × Höhe) für Rasterbilder.
        'max_pixels' => (int) env('DOCUMENT_DESIGN_MAX_PIXELS', 40_000_000),
        // Mindestbreite in Pixeln, damit der Bogen druckfähig bleibt.
        'min_width_px' => 500,
    ],

    // Ziel-DPI der normalisierten Rasterrepräsentation.
    'render_dpi' => (int) env('DOCUMENT_DESIGN_RENDER_DPI', 150),

    // Toleranz für das A4-Hochformat-Seitenverhältnis (relativ).
    'aspect_tolerance' => 0.03,

    // Mindestmaße des nutzbaren Inhaltsbereichs in Millimetern (Preflight).
    'min_content_mm' => [
        'width' => 80,
        'height' => 80,
    ],

    // Mindestkontrast (WCAG) für Tabellentext im Preflight.
    'min_contrast' => 4.5,
];
