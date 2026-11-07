{{--
  Created on   : Sat Nov 07 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _link_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Weitere Rechnung des Anbieters zuordnen (Feature 196): geteilte, gebündelte oder direkt extern angelegte
     Rechnung. Je Position genau eine Rechnung. Erwartet: $transfer, $provider, $candidates, $positions. --}}
<x-modal
    :title="__('finance.external_invoice.link.title')"
    :eyebrow="__('finance.external_invoice.positions.transfer_label', ['id' => $transfer->id])"
    icon="add_link"
    tone="primary"
    :action="route('finance.transfers.external-invoice.link', $transfer)"
    method="POST"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('finance.external_invoice.link.submit')"
>
    <div class="space-y-4">
        <p class="text-sm text-base-content/70">{{ __('finance.external_invoice.link.intro', ['program' => $provider?->label() ?? '—']) }}</p>
        @if ($candidates !== [])
            <x-select-field name="external_candidate" :label="__('finance.external_invoice.link.candidate')">
                <option value="">{{ __('finance.external_invoice.link.candidate_none') }}</option>
                @foreach ($candidates as $candidate)
                    <option value="{{ $candidate['external_id'] }}">{{ $candidate['number'] ?? $candidate['external_id'] }}@if ($candidate['date']) · {{ \Illuminate\Support\Carbon::parse($candidate['date'])->fdate() }}@endif</option>
                @endforeach
            </x-select-field>
        @endif
        <x-input-field name="external_id" :label="__('finance.external_invoice.link.external_id')" maxlength="128"
                       :hint="__('finance.external_invoice.link.external_id_hint')" />
        <fieldset class="space-y-1">
            <legend class="text-sm font-medium">{{ __('finance.external_invoice.link.positions') }}</legend>
            @forelse ($positions as $position)
                <label class="flex cursor-pointer items-center gap-2 text-sm">
                    <input type="checkbox" name="positions[]" value="{{ $position->sqid }}" class="checkbox checkbox-sm">
                    <span>{{ $position->position }}. {{ $position->name }}</span>
                    <span class="ml-auto tabular-nums text-base-content/70">{{ \CommonToolkit\ValueObjects\Decimal::of((string) $position->amount, 2)->format() }}</span>
                </label>
            @empty
                <p class="text-sm text-base-content/70">{{ __('finance.external_invoice.link.no_positions') }}</p>
            @endforelse
        </fieldset>
    </div>
</x-modal>
