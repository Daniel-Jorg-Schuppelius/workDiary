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
        <button type="button" class="btn btn-sm btn-outline schedule-print-pick"
                data-print-src="{{ route('print.schedule', ['from' => $from->toDateString(), 'to' => $to->toDateString()]) }}">
            <x-icon name="print" />{{ __('Schichtplan (A4 quer)') }}
        </button>
        <button type="button" class="btn btn-sm btn-outline schedule-print-pick"
                data-print-src="{{ route('print.on-call') }}">
            <x-icon name="print" />{{ __('Bereitschaft & Notdienst (A4 quer)') }}
        </button>
        <button type="button" class="btn btn-sm btn-outline schedule-print-pick"
                data-print-src="{{ route('print.vacations', ['year' => $anchor->year]) }}">
            <x-icon name="print" />{{ __('Urlaubsübersicht ') . $anchor->year . __(' (A4 hoch)') }}
        </button>
    </div>

    <iframe id="schedule-print-frame" title="{{ __('Druckvorschau') }}"
            class="mt-3 w-full rounded-box border border-base-300 bg-white"
            style="height:60vh"></iframe>

    <x-slot:actions>
        <a id="schedule-print-newtab" href="#" target="_blank" rel="noopener"
           class="btn btn-ghost btn-sm gap-2 hidden">
            <x-icon name="open_in_new" />{{ __('In neuem Tab öffnen') }}
        </a>
        <button type="button" class="btn btn-ghost gap-2" data-entry-modal-close>
            <x-icon name="close" />{{ __('Schließen') }}
        </button>
        <button type="button" id="schedule-print-now" class="btn btn-primary gap-2" disabled>
            <x-icon name="print" />{{ __('Drucken') }}
        </button>
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
        <span class="label-text">{{ __('CSV-Datei (max. :mb MB, :rows Zeilen)', ['mb' => 5, 'rows' => number_format(50000, 0, ',', '.')]) }}</span>
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
