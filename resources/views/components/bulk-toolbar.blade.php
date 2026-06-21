@props([
    'rootSelector' => '[data-bulk-form]',
    'inputName' => 'ids',
    'tone' => 'primary',
    'icon' => 'checklist',
    'label' => null,
])

{{--
    <x-bulk-toolbar> — Sticky-Leiste für Massenaktionen.

    Wird oberhalb der Tabelle gerendert und blendet sich automatisch ein/aus
    (siehe bulk-selection.js), abhängig von der Anzahl ausgewählter Zeilen.

    Erwartete Markup-Konventionen in der umgebenden Form:
      <form data-bulk-form data-bulk-target="<aktion-url-fallback>">
          <input data-bulk-select-all type="checkbox">
          ... <input data-bulk-checkbox type="checkbox" name="{{ $inputName }}[]" value="…">
          <x-bulk-toolbar>
              <x-slot:actions>
                  <button type="submit" formaction="{{ route('...bulkApprove') }}" class="btn btn-success btn-sm">…</button>
              </x-slot:actions>
          </x-bulk-toolbar>
      </form>

    Slots:
      - default / actions : Action-Buttons (formaction setzen!)
--}}

<div data-bulk-toolbar
     class="alert alert-{{ $tone }} alert-soft hidden items-center gap-3 sticky top-2 z-20 shadow-sm"
     role="region"
     aria-label="{{ __('Massenaktionen') }}">
    <x-icon :name="$icon" />
    <div class="flex-1">
        <strong data-bulk-counter>0</strong>
        <span>{{ $label ?? __(':n Einträge ausgewählt') }}</span>
    </div>
    <div class="flex flex-wrap items-center gap-2">
        {{ $actions ?? $slot }}
        <x-button type="button" tone="ghost" size="xs" icon="close" data-bulk-clear>
            {{ __('Auswahl aufheben') }}
        </x-button>
    </div>
</div>
