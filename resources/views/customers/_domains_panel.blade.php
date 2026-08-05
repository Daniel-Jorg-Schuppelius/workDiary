{{--
  Created on   : Sat Jul 18 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _domains_panel.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}

{{-- Kundenakte-Reiter „Domains" (Feature 083, MVP-394; Vollaudit 2026-07,
     M34): direkt zugeordnete Domains + Domains der zugeordneten
     Reseller-Accounts; „Domain hinzufügen" führt in den Registrierungsdialog
     der Domainverwaltung (Kundenkontext vorbelegt). --}}

@can(\App\Enums\User\Permission::DomainViewAny->value)
    <x-card :title="__('Domains')" padding="p-0" id="customer-domains">
        <div class="flex items-center justify-between gap-2 border-b border-base-300 px-4 py-2">
            <span class="text-sm text-base-content/70">
                {{ trans_choice(':count Domain|:count Domains', $customerDomains->count(), ['count' => $customerDomains->count()]) }}
            </span>
            <x-icon-btn icon="add" tone="ghost" size="sm"
                        :href="route('domains.index', ['customer' => $customer->sqid])"
                        show-label>{{ __('Domain hinzufügen') }}</x-icon-btn>
        </div>
        <x-table bare>
            <x-slot:head>
                <tr>
                    <th>{{ __('Domain') }}</th>
                    <th>{{ __('Endkunde') }}</th>
                    <th>{{ __('Registrar') }}</th>
                    <th>{{ __('Status') }}</th>
                    <th>{{ __('Läuft ab') }}</th>
                </tr>
            </x-slot:head>
            @forelse ($customerDomains as $domain)
                <tr>
                    <td><a href="{{ route('domains.show', $domain) }}" class="link link-hover">{{ $domain->external_domain }}</a></td>
                    <td class="text-sm">{{ $domain->foreignCustomer?->name ?? '—' }}</td>
                    <td class="text-sm">{{ $domain->registrar ?? '—' }}</td>
                    <td class="text-sm">{{ $domain->status ?? '—' }}</td>
                    <td class="tabular-nums text-sm">{{ $domain->expiration_at?->fdate() ?? '—' }}</td>
                </tr>
            @empty
                <x-table.empty :colspan="5" :title="__('Keine Domains zugeordnet.')" compact />
            @endforelse
        </x-table>
    </x-card>
@endcan
