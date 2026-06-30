@props([
    'colspan' => 1,
    'icon'    => null,
    'title'   => null,
    'message' => null,
    'tone'    => 'ghost',
    'compact' => false,
])

{{--
    <x-table.empty> — Empty-State-Zeile für <x-table>.

    Rendert eine <tr data-sort-ignore> mit einer einzigen <td colspan="…">, in
    der eine <x-empty-state>-Box steht. data-sort-ignore verhindert, dass die
    Zeile beim client-seitigen Sortieren mitwandert.

    Default: zeigt ein generisches Inbox-Icon und eine erklärende Message, damit
    alle Tabellen im Projekt das gleiche Empty-State-Erscheinungsbild haben.
    Aufrufer können `icon`, `title`, `message` für spezifischere Hinweise
    überschreiben.
--}}

@php
    $iconResolved = $icon ?? '<span class="material-symbols-outlined" aria-hidden="true">inbox</span>';
    $titleResolved = $title ?? __('Keine Einträge vorhanden');
    $messageResolved = $message ?? __('Für die aktuelle Auswahl wurden keine Daten gefunden.');
@endphp

<tr data-sort-ignore>
    <td colspan="{{ (int) $colspan }}" class="bg-base-100! p-4!">
        <x-empty-state
            :icon="$iconResolved"
            :title="$titleResolved"
            :message="$messageResolved"
            :tone="$tone"
            :compact="$compact"
        />
    </td>
</tr>
