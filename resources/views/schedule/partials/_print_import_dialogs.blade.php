{{--
  Created on   : Mon Jun 15 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _print_import_dialogs.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{--
    Druck- und Import-Dialoge für den Schichtplan (nur Admin). Echte
    <dialog>-Modals (x-modal embedded=false) statt CSS-Dropdown bzw.
    Vollseiten-Navigation — robust gegen den overflow:clip-Container der Seite.
    Trigger-Buttons (#btn-open-print / #btn-open-import) stehen in der Filterbar.
    Erwartet aus dem View-Scope: $anchor (für das Urlaubsjahr).
--}}

{{-- Drucken: Übersicht wählen → Vorschau im iframe → direkt aus dem Dialog drucken
     (kein neuer Tab). „In neuem Tab öffnen" bleibt als Fallback. --}}
<x-modal id="schedule-print-dialog" :embedded="false" icon="print" size="wide"
         :eyebrow="__('Schichtplan')" :title="__('Drucken')">
    <div class="flex flex-wrap gap-2">
        <x-button type="button" tone="outline" class="schedule-print-pick"
                data-print-src="{{ route('print.schedule', ['from' => $from->toDateString(), 'to' => $to->toDateString()]) }}" icon="print">{{ __('Schichtplan (A4 quer)') }}</x-button>
        <x-button type="button" tone="outline" class="schedule-print-pick"
                data-print-src="{{ route('print.on-call') }}" icon="print">{{ __('Bereitschaft & Notdienst (A4 quer)') }}</x-button>
        <x-button type="button" tone="outline" class="schedule-print-pick"
                data-print-src="{{ route('print.vacations', ['year' => $anchor->year]) }}" icon="print">{{ __('Urlaubsübersicht ') . $anchor->year . __(' (A4 hoch)') }}</x-button>
    </div>

    <iframe id="schedule-print-frame" title="{{ __('Druckvorschau') }}"
            class="mt-3 w-full rounded-box border border-base-300 bg-white"
            style="height:60vh"></iframe>

    <x-slot:actions>
        <x-button id="schedule-print-newtab" href="#" target="_blank" rel="noopener"
           tone="ghost" class="gap-2 hidden" icon="open_in_new">{{ __('In neuem Tab öffnen') }}</x-button>
        <x-button type="button" tone="ghost" size="md" class="gap-2" data-entry-modal-close icon="close">{{ __('Schließen') }}</x-button>
        <x-button type="button" id="schedule-print-now" tone="primary" size="md" class="gap-2" disabled icon="print">{{ __('Drucken') }}</x-button>
    </x-slot:actions>
</x-modal>

{{-- Import: CSV-Upload für Schichten (Entität fix „scheduled_shifts"), postet
     wie bisher zur Vorprüfung; die Vorschau-Seite folgt als nächster Schritt. --}}
<x-modal id="schedule-import-dialog" :embedded="false" icon="upload"
         :eyebrow="__('Schichtplan')" :title="__('Schichten importieren')"
         :action="route('admin.imports.preflight')" method="POST"
         enctype="multipart/form-data" :submit-label="__('Vorprüfung starten')">
    <input type="hidden" name="entity" value="scheduled_shifts">
    <label class="form-control">
        <span class="label-text">{{ __('CSV-Datei (max. :mb MB, :rows Zeilen)', ['mb' => 5, 'rows' => \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat(50000, 0, withThousandsSeparator: true)]) }}</span>
        <input type="file" name="file" required accept=".csv,.txt"
               class="file-input file-input-sm file-input-bordered w-full" />
    </label>
    @error('file')<div class="text-error text-sm">{{ $message }}</div>@enderror
    @error('entity')<div class="text-error text-sm">{{ $message }}</div>@enderror
    <p class="text-sm text-base-content/70">
        {{ __('Trennzeichen wird automatisch erkannt (Semikolon, Komma, Tab). Spaltenüberschriften können deutsch oder englisch sein.') }}
    </p>
</x-modal>

<script @cspNonce>
document.addEventListener('DOMContentLoaded', function () {
    const printDialog = document.getElementById('schedule-print-dialog');
    const frame = document.getElementById('schedule-print-frame');
    const printNow = document.getElementById('schedule-print-now');
    const newTab = document.getElementById('schedule-print-newtab');
    const picks = Array.from(document.querySelectorAll('.schedule-print-pick'));

    const selectPick = function (btn) {
        const src = btn.getAttribute('data-print-src');
        if (frame) frame.src = src;
        if (printNow) printNow.disabled = false;
        if (newTab) { newTab.href = src; newTab.classList.remove('hidden'); }
        picks.forEach(b => b.classList.remove('btn-active'));
        btn.classList.add('btn-active');
    };

    picks.forEach(btn => btn.addEventListener('click', () => selectPick(btn)));

    printNow?.addEventListener('click', function () {
        if (!frame || !frame.getAttribute('src')) return;
        frame.contentWindow.focus();
        frame.contentWindow.print();
    });

    document.getElementById('btn-open-print')?.addEventListener('click', function () {
        printDialog.showModal();
        // Erste Übersicht direkt laden, falls noch keine gewählt wurde.
        if (frame && !frame.getAttribute('src') && picks.length) selectPick(picks[0]);
    });

    document.getElementById('btn-open-import')?.addEventListener('click', function () {
        document.getElementById('schedule-import-dialog').showModal();
    });
    @if ($errors->has('file') || $errors->has('entity'))
        document.getElementById('schedule-import-dialog')?.showModal();
    @endif
});
</script>
