{{--
  Created on   : Fri Aug 08 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _canvas.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}

{{-- Datencontainer für die optionale Chart.js-Verbesserung am Bildschirm
     (Progressive Enhancement, resources/js/charts.js). Standardmäßig
     versteckt: erst wenn das Enhancement das Canvas erfolgreich rendert, wird
     der Container sichtbar und das SVG ausgeblendet. Ohne JS / im PDF bleibt
     das SVG die Darstellung. $spec ist der JSON-Serialisierbare Diagramm-
     Kontrakt (siehe charts.js). --}}

@php
    $chartJson = json_encode($spec, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $categoryCount = is_array($spec['labels'] ?? null) ? count($spec['labels']) : 0;
    // Kategorien wachsen in die Höhe: liegende Balken brauchen Platz je Zeile,
    // stehende ab ~9 Kategorien für die gedrehten Fußlabels. Ohne das werden
    // Balken bei Top-20-Listen zu Strichen.
    $sideways = ($spec['horizontal'] ?? false) === true
        || ($spec['bullet'] ?? false) === true
        || ($spec['type'] ?? '') === 'boxplot';
    $heightClass = match (true) {
        $sideways && $categoryCount > 14 => 'h-[32rem]',
        $sideways && $categoryCount > 8 => 'h-96',
        !$sideways && $categoryCount > 8 => 'h-72 sm:h-80',
        default => 'h-64 sm:h-72',
    };
@endphp
@if ($chartJson !== false)
    <div class="wd-chart-canvas mt-2 {{ $heightClass }}" data-wd-chart="{{ $chartJson }}" hidden></div>
@endif
