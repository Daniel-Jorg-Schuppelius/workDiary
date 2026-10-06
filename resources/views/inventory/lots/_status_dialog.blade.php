{{--
  Created on   : Mon Oct 05 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _status_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Charge sperren oder freigeben (Feature 047/048) — jeweils mit Begründung.
  Erwartet: $lot (StockLot), $blocking (bool: sperren, sonst freigeben).
--}}
<x-modal
    :title="$blocking ? __('inventory.lot.block.title') : __('inventory.lot.unblock.title')"
    :eyebrow="$lot->lot_no"
    :icon="$blocking ? 'lock' : 'lock_open'"
    :tone="$blocking ? 'warning' : 'primary'"
    :action="$blocking ? route('inventory.lots.block.store', $lot) : route('inventory.lots.unblock.store', $lot)"
    method="POST"
    :form-data="['data-entry-form' => '']"
    :submit-label="$blocking ? __('inventory.lot.block.action') : __('inventory.lot.unblock.action')">
    <p class="text-sm text-muted">{{ $blocking ? __('inventory.lot.block.hint') : __('inventory.lot.unblock.hint') }}</p>
    @if (! $blocking && $lot->blocked_reason)
        <p class="text-sm">{{ __('inventory.lot.blocked_because', ['reason' => $lot->blocked_reason]) }}</p>
    @endif
    <x-input-field name="reason" :label="__('inventory.lot.reason')" :value="old('reason')" required maxlength="500" />
</x-modal>
