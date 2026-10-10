{{--
  Created on   : Sun Oct 04 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : report-export.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@props([
    'url',                               // fn (string $format): string — Link je Format
    'formats' => ['pdf', 'csv', 'xlsx'], // ohne 'pdf' entfällt der PDF-Knopf
    'tone'    => 'outline',
])

{{--
    <x-report-export> — Exportleiste eines Berichts: PDF als Knopf, CSV und
    Excel im Menü; weitere Menüeinträge (Nachweise) im Slot.

    Ohne Exportrecht erscheint nichts (E10, MVP-1101): Der Controller der Seite
    entscheidet über mayExportReport() (WritesReportCsv).

    Beispiel:
        <x-report-export :url="fn (string $format) => route('reports.absences', array_merge($linkParams, ['export' => $format]))" />
--}}
@php
    $controller = request()->route()?->getController();
    $exportAllowed = ! is_object($controller) || ! method_exists($controller, 'mayExportReport') || $controller->mayExportReport(request());
    $menuFormats = array_values(array_intersect(['csv', 'xlsx'], $formats));
@endphp
@if ($exportAllowed)
    @if (in_array('pdf', $formats, true))
        <x-icon-btn icon="picture_as_pdf" :tone="$tone" size="sm" :href="$url('pdf')" show-label>PDF</x-icon-btn>
    @endif
    @if ($menuFormats !== [] || $slot->isNotEmpty())
        <x-action-menu icon="download" :tone="$tone" :label="__('Export')">
            @if (in_array('csv', $menuFormats, true))
                <x-icon-btn icon="download" :tone="$tone" size="sm" :href="$url('csv')" show-label>CSV</x-icon-btn>
            @endif
            @if (in_array('xlsx', $menuFormats, true))
                <x-icon-btn icon="table_view" :tone="$tone" size="sm" :href="$url('xlsx')" show-label>Excel</x-icon-btn>
            @endif
            {{ $slot }}
        </x-action-menu>
    @endif
@endif
