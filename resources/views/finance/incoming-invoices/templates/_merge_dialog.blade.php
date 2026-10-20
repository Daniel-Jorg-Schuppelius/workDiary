{{--
  Created on   : Tue Oct 20 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _merge_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Belegvorlage zusammenführen (Phase 155, MVP-1253): Quelle geht in der Zielvorlage auf. Variablen: $template, $targets --}}
<x-modal
    :title="__('Vorlage „:name“ zusammenführen', ['name' => $template->name])"
    :eyebrow="__('Belegvorlagen')"
    icon="merge"
    tone="warning"
    :action="route('finance.incoming-invoices.templates.merge', $template)"
    method="POST"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('Zusammenführen')">

    <x-select-field name="target" :label="__('Zielvorlage')" required :hint="__('Zonen für Felder, die der Zielvorlage fehlen, wandern mit; die Eingänge werden umgehängt, Treffer addiert. Name und Fingerabdruck der Zielvorlage bleiben, diese Vorlage wird gelöscht.')">
        <option value="">—</option>
        @foreach ($targets as $target)
            <option value="{{ $target->sqid }}" @selected(old('target') === $target->sqid)>{{ $target->name }}@if ($target->supplier) · {{ $target->supplier->name }}@endif ({{ trans_choice(':count Treffer|:count Treffer', $target->hits, ['count' => $target->hits]) }})</option>
        @endforeach
    </x-select-field>
</x-modal>
