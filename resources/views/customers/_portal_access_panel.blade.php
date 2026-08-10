{{--
  Created on   : Mon Aug 10 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _portal_access_panel.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}

{{-- Kundenakte-Panel „Portalzugänge" (MVP-510) — erwartet: $customer,
     $portalUsers (Collection<User>), $portalLastLogins (array user_id => Carbon|null).
     Ein Zugang allein gibt keine Inhalte frei; die sichtbaren Bereiche
     steuert die Portal-Konfiguration (MVP-511). --}}

@can(\App\Enums\User\Permission::CustomerPortalAccessManage->value)
    @php $portalService = app(\App\Services\CustomerPortal\PortalAccessService::class); @endphp
    <x-card :title="__('Portalzugänge')" padding="p-0" id="portal-access">
        <div class="flex items-center justify-between gap-2 border-b border-base-300 px-4 py-2">
            <span class="text-sm text-base-content/70">
                {{ trans_choice(':count Zugang|:count Zugänge', $portalUsers->count(), ['count' => $portalUsers->count()]) }}
            </span>
            <x-icon-btn icon="person_add" tone="ghost" size="sm"
                        data-entry-modal-trigger
                        :href="route('customers.portal-access.create', $customer)"
                        show-label>{{ __('Zugang einladen') }}</x-icon-btn>
        </div>
        <x-table bare>
            <x-slot:head>
                <tr>
                    <th>{{ __('Name') }}</th>
                    <th>{{ __('E-Mail') }}</th>
                    <th>{{ __('Status') }}</th>
                    <th>{{ __('Eingeladen') }}</th>
                    <th>{{ __('Letzte Anmeldung') }}</th>
                    <th>{{ __('2FA') }}</th>
                    <th></th>
                </tr>
            </x-slot:head>
            @forelse ($portalUsers as $portalUser)
                @php $state = $portalService->state($portalUser); @endphp
                <tr>
                    <td class="text-sm font-medium">{{ $portalUser->name }}</td>
                    <td class="text-sm">{{ $portalUser->email }}</td>
                    <td>
                        @switch($state)
                            @case(\App\Services\CustomerPortal\PortalAccessService::STATE_ACTIVE)
                                <x-status-badge tone="success" size="xs">{{ __('aktiv') }}</x-status-badge>
                                @break
                            @case(\App\Services\CustomerPortal\PortalAccessService::STATE_INVITED)
                                <x-status-badge tone="info" size="xs">{{ __('eingeladen') }}</x-status-badge>
                                @break
                            @case(\App\Services\CustomerPortal\PortalAccessService::STATE_EXPIRED)
                                <x-status-badge tone="warning" size="xs">{{ __('Einladung abgelaufen') }}</x-status-badge>
                                @break
                            @default
                                <x-status-badge tone="error" size="xs">{{ __('deaktiviert') }}</x-status-badge>
                        @endswitch
                    </td>
                    <td class="text-sm tabular-nums">{{ $portalUser->portal_invited_at?->fdate() ?? '—' }}</td>
                    <td class="text-sm tabular-nums">{{ ($portalLastLogins[$portalUser->id] ?? null)?->fdate() ?? '—' }}</td>
                    <td class="text-sm">
                        @if ($portalUser->hasTwoFactorEnabled())
                            <x-status-badge tone="success" size="xs">{{ __('aktiv') }}</x-status-badge>
                        @else
                            <span class="text-base-content/50">—</span>
                        @endif
                    </td>
                    <td class="text-right whitespace-nowrap">
                        @if ($state === \App\Services\CustomerPortal\PortalAccessService::STATE_DEACTIVATED)
                            <form method="POST" action="{{ route('customers.portal-access.reactivate', [$customer, $portalUser]) }}"
                                  data-confirm-dialog
                                  data-confirm-title="{{ __('Portalzugang reaktivieren') }}"
                                  data-confirm-label="{{ __('Reaktivieren') }}"
                                  class="inline">
                                @csrf
                                <x-icon-btn icon="person_check" tone="success" type="submit" :label="__('Reaktivieren')" />
                            </form>
                        @else
                            @if ($state !== \App\Services\CustomerPortal\PortalAccessService::STATE_ACTIVE)
                                <form method="POST" action="{{ route('customers.portal-access.resend', [$customer, $portalUser]) }}" class="inline">
                                    @csrf
                                    <x-icon-btn icon="forward_to_inbox" type="submit" :label="__('Einladung erneut senden')" />
                                </form>
                            @endif
                            <form method="POST" action="{{ route('customers.portal-access.deactivate', [$customer, $portalUser]) }}"
                                  data-confirm-dialog
                                  data-confirm-title="{{ __('Portalzugang deaktivieren') }}"
                                  data-confirm-message="{{ __('Der Zugang wird sofort abgemeldet und kann sich nicht mehr anmelden. Fachnachweise bleiben erhalten.') }}"
                                  data-confirm-tone="error"
                                  data-confirm-label="{{ __('Deaktivieren') }}"
                                  class="inline">
                                @csrf
                                <x-icon-btn icon="person_off" tone="error" type="submit" :label="__('Deaktivieren')" />
                            </form>
                        @endif
                    </td>
                </tr>
            @empty
                <x-table.empty :colspan="7" :title="__('Noch keine Portalzugänge — lade den ersten Kontakt ein.')" compact />
            @endforelse
        </x-table>
    </x-card>
@endcan
