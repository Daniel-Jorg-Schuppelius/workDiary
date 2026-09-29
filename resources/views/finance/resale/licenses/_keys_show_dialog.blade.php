{{--
  Created on   : Tue Sep 29 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _keys_show_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Schlüsselsatz im Klartext (MVP-1024): nur mit reselling.keys.view,
  Antwort no-store, Zugriff protokolliert. Kopieren je Schlüssel.
--}}
<x-modal
    :title="__('resale.license.action.show_keys')"
    :eyebrow="$unit->batch->product->name . ' · ' . $unit->label()"
    icon="key" tone="warning" size="md">
    <p class="mb-3 text-sm text-muted">{{ __('resale.license.hint.keys_show') }}</p>
    @if ($unit->activeAssignment !== null)
        <p class="mb-3 text-sm">{{ __('resale.license.keys_sold_to', ['customer' => $unit->activeAssignment->holderLabel(), 'date' => $unit->activeAssignment->sold_on->fdate()]) }}</p>
    @endif
    <div class="flex flex-col gap-3">
        @foreach ($keys as $key)
            <div class="flex items-end gap-2">
                <label class="form-control grow">
                    <span class="label-text text-sm">{{ $key['label'] }}</span>
                    @if ($key['value'] !== null)
                        <input id="license-key-value-{{ $key['role'] }}" type="text" readonly value="{{ $key['value'] }}" class="input input-sm input-bordered font-mono" autocomplete="off" spellcheck="false">
                    @else
                        <span class="text-sm text-warning">{{ __('resale.license.hint.key_missing') }}</span>
                    @endif
                </label>
                @if ($key['value'] !== null)
                    <x-icon-btn icon="content_copy" size="sm" tone="ghost" :data-copy-target="'license-key-value-' . $key['role']" :data-copy-feedback="__('resale.license.copied')" :title="__('resale.license.action.copy')" />
                @endif
            </div>
        @endforeach
    </div>
</x-modal>
