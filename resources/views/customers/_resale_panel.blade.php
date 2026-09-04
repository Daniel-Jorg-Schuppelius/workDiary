{{--
  Created on   : Fri Sep 04 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _resale_panel.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}

{{-- Kundenakte-Reiter „Abos & Lizenzen" (Feature 152, MVP-758): Abos, die der
     Kunde selbst hält, und Abos seiner Fremdkunden (Endkunden), für die er
     die Rechnung bekommt. „Abo anlegen" öffnet den Dialog mit vorbelegtem
     Kunden. --}}

@can(\App\Enums\User\Permission::ResellingView->value)
    <x-card :title="__('resale.title.menu')" padding="p-0" id="customer-resale">
        <div class="flex items-center justify-between gap-2 border-b border-base-300 px-4 py-2">
            <span class="text-sm text-base-content/70">
                {{ trans_choice('resale.customer_panel.count', $customerSubscriptions->count(), ['count' => $customerSubscriptions->count()]) }}
            </span>
            <div class="flex items-center gap-1">
                <x-icon-btn icon="list" tone="ghost" size="sm"
                            :href="route('finance.resale.index', ['customer' => $customer->sqid])"
                            show-label>{{ __('resale.customer_panel.all') }}</x-icon-btn>
                @can(\App\Enums\User\Permission::ResellingManage->value)
                    <x-icon-btn icon="add" tone="ghost" size="sm" data-entry-modal-trigger
                                :href="route('finance.resale.create', ['customer' => $customer->sqid])"
                                show-label>{{ __('resale.action.new') }}</x-icon-btn>
                @endcan
            </div>
        </div>
        <x-table bare>
            <x-slot:head>
                <tr>
                    <th>{{ __('resale.field.label') }}</th>
                    <th>{{ __('resale.field.holder') }}</th>
                    <th class="text-right">{{ __('resale.field.quantity') }}</th>
                    <th>{{ __('resale.field.starts_on') }}</th>
                    <th>{{ __('resale.field.status') }}</th>
                    <th class="text-right">{{ __('resale.field.open_periods') }}</th>
                </tr>
            </x-slot:head>
            @forelse ($customerSubscriptions->take(10) as $subscription)
                <tr>
                    <td><a href="{{ route('finance.resale.show', $subscription->sqid) }}" class="link link-hover">{{ $subscription->label }}</a></td>
                    <td class="text-sm">{{ $subscription->foreignCustomer?->name ?? __('resale.holder.customer') }}</td>
                    <td class="text-right tabular-nums">{{ $subscription->quantity }}</td>
                    <td class="tabular-nums text-sm">{{ $subscription->starts_on->format('d.m.Y') }}</td>
                    <td><x-status-badge size="xs" :tone="$subscription->status->tone()" :label="$subscription->status->label()" /></td>
                    <td class="text-right tabular-nums">
                        @if ($subscription->open_periods_count > 0)
                            <span class="badge badge-error badge-sm">{{ $subscription->open_periods_count }}</span>
                        @else
                            <span class="text-muted">0</span>
                        @endif
                    </td>
                </tr>
            @empty
                <x-table.empty :colspan="6" :title="__('resale.customer_panel.empty')" compact />
            @endforelse
        </x-table>
        @if ($customerSubscriptions->count() > 10)
            <div class="border-t border-base-300 px-4 py-2 text-xs text-muted">
                {{ __('resale.customer_panel.more', ['count' => $customerSubscriptions->count() - 10]) }}
            </div>
        @endif
    </x-card>
@endcan
