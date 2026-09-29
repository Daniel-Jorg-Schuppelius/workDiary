{{--
  Created on   : Tue Sep 29 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _unit_row.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Zeile einer Einzellizenz (MVP-1024): Status, Zahl der Schlüssel (nie der
  Wert), Halter und die Aktionen je Status. Erwartet: $unit (withStockState),
  $showProduct.
--}}
@php
    $status = $unit->status();
    $assignment = $unit->activeAssignment;
    $canManage = auth()->user()?->can(\App\Enums\User\Permission::ResellingManage->value) ?? false;
@endphp
<tr class="hover">
    <td class="whitespace-nowrap">
        <a href="{{ route('finance.resale.licenses.batches.show', $unit->batch) }}" class="link link-hover font-medium">{{ $unit->label() }}</a>
    </td>
    @if ($showProduct)
        <td class="text-sm">{{ $unit->batch->product->name }}</td>
    @endif
    <td>
        <x-status-badge size="xs" :tone="$status->tone()" :label="$status->label()" />
        @if ($status === \App\Enums\Reselling\LicenseUnitStatus::Blocked && $unit->blocked_reason)
            <span class="block text-xs text-muted">{{ $unit->blocked_reason }}</span>
        @endif
    </td>
    <td class="tabular-nums text-sm">{{ __('resale.license.keys_count', ['count' => $unit->keys_count, 'total' => $unit->batch->key_count]) }}</td>
    <td class="text-sm">
        @if ($assignment !== null)
            {{ $assignment->holderLabel() }}
            @if ($assignment->foreignCustomer !== null)
                <span class="block text-xs text-muted">{{ __('resale.holder.via', ['partner' => $assignment->customer->name]) }}</span>
            @endif
        @else
            <span class="text-muted">—</span>
        @endif
    </td>
    <td class="tabular-nums text-sm whitespace-nowrap">{{ $assignment?->sold_on->fdate() ?? '—' }}</td>
    <td class="text-sm">{{ $assignment?->invoice_reference ?? '—' }}</td>
    <td class="text-right">
        <div class="flex justify-end gap-1">
            @can(\App\Enums\User\Permission::ResellingKeysView->value)
                @if ($unit->keys_count > 0)
                    <x-icon-btn icon="key" size="xs" tone="ghost" data-entry-modal-trigger :href="route('finance.resale.licenses.units.keys.show', $unit)" :title="__('resale.license.action.show_keys')" />
                @endif
            @endcan
            @if ($canManage)
                @if ($assignment === null)
                    <x-icon-btn icon="edit_note" size="xs" tone="ghost" data-entry-modal-trigger :href="route('finance.resale.licenses.units.keys.edit', $unit)" :title="__('resale.license.action.edit_keys')" />
                @endif
                @if ($status === \App\Enums\Reselling\LicenseUnitStatus::Available)
                    <x-icon-btn icon="add_shopping_cart" size="xs" tone="ghost" data-entry-modal-trigger :href="route('finance.resale.licenses.sell.create', ['unit' => $unit->sqid])" :title="__('resale.license.action.sell')" />
                @endif
                @if ($assignment !== null)
                    <x-icon-btn icon="swap_horiz" size="xs" tone="ghost" data-entry-modal-trigger :href="route('finance.resale.licenses.assignments.reassign.create', $assignment)" :title="__('resale.license.action.reassign')" />
                    <x-icon-btn icon="undo" size="xs" tone="ghost" data-entry-modal-trigger :href="route('finance.resale.licenses.assignments.return.create', $assignment)" :title="__('resale.license.action.return')" />
                @elseif ($unit->blocked_at !== null)
                    <x-icon-btn icon="lock_open" size="xs" tone="ghost" data-entry-modal-trigger :href="route('finance.resale.licenses.units.unblock.create', $unit)" :title="__('resale.license.action.unblock')" />
                @else
                    <x-icon-btn icon="block" size="xs" tone="ghost" data-entry-modal-trigger :href="route('finance.resale.licenses.units.block.create', $unit)" :title="__('resale.license.action.block')" />
                @endif
            @endif
        </div>
    </td>
</tr>
