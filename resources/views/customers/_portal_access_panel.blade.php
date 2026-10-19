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
     steuert die Portal-Konfiguration (MVP-511). Phase 153: Vorschau als Kunde
     (customerPortal.preview) und Zugang mit Startpasswort. --}}

@can(\App\Enums\User\Permission::CustomerPortalAccessManage->value)
    @php
        $portalService = app(\App\Services\CustomerPortal\PortalAccessService::class);
        $portalPreview = app(\App\Services\CustomerPortal\PortalPreview::class);
        $canPreview = auth()->user()?->isAdmin() || \Illuminate\Support\Facades\Gate::allows(\App\Enums\User\Permission::CustomerPortalPreview->value);
        // Vorschau braucht ein Konto: erst ein aktives, sonst das erste nicht deaktivierte (E1).
        $previewUser = $portalUsers->first(fn ($u) => $portalService->state($u) === \App\Services\CustomerPortal\PortalAccessService::STATE_ACTIVE)
            ?? $portalUsers->first(fn ($u) => $portalService->state($u) !== \App\Services\CustomerPortal\PortalAccessService::STATE_DEACTIVATED);
        $previewState = $portalPreview->runsFor(request(), $customer) ? $portalPreview->state(request()) : null;
        $previewName = $previewState !== null ? ($portalUsers->firstWhere('id', (int) ($previewState['portal_user_id'] ?? 0))?->name ?? '—') : null;
        // Einmalige Anzeige eines Startpassworts (MVP-1232, Muster API-Token).
        $startPassword = session('portal_start_password');
        $startPasswordEmail = session('portal_start_password_email');
    @endphp
    <x-card :title="__('Portalzugänge')" padding="p-0" id="portal-access">
        <div class="flex flex-wrap items-center justify-between gap-2 border-b border-base-300 px-4 py-2">
            <span class="text-sm text-base-content/70">
                {{ trans_choice(':count Zugang|:count Zugänge', $portalUsers->count(), ['count' => $portalUsers->count()]) }}
            </span>
            <div class="flex flex-wrap items-center gap-1">
                @if ($canPreview)
                    @if ($previewUser !== null)
                        <form method="POST" action="{{ route('customers.portal-preview.start', [$customer, $previewUser]) }}" target="_blank" class="inline">
                            @csrf
                            <x-icon-btn icon="visibility" tone="ghost" size="sm" type="submit" show-label>{{ __('customer_portal.preview.action') }}</x-icon-btn>
                        </form>
                    @else
                        <span class="text-xs text-muted">{{ __('customer_portal.preview.no_account') }}</span>
                    @endif
                @endif
                <x-icon-btn icon="key" tone="ghost" size="sm"
                            data-entry-modal-trigger
                            :href="route('customers.portal-access.create-direct', $customer)"
                            show-label>{{ __('customer_portal.direct.action') }}</x-icon-btn>
                <x-icon-btn icon="person_add" tone="ghost" size="sm"
                            data-entry-modal-trigger
                            :href="route('customers.portal-access.create', $customer)"
                            show-label>{{ __('Zugang einladen') }}</x-icon-btn>
            </div>
        </div>
        @if (is_string($startPassword) && $startPassword !== '')
            <div role="status" class="m-4 rounded-box border border-warning/40 bg-warning/10 p-4">
                <p class="font-semibold">{{ __('customer_portal.direct.password_heading', ['email' => (string) $startPasswordEmail]) }}</p>
                <div class="mt-2 flex flex-wrap items-center gap-2">
                    <code id="portal-start-password" class="select-all rounded bg-base-200 px-3 py-2 font-mono text-lg tracking-wider">{{ $startPassword }}</code>
                    {{-- Ohne Symbol: data-copy-feedback ersetzt den gesamten Knopftext. --}}
                    <x-button type="button" tone="ghost" size="sm"
                              data-copy-target="portal-start-password"
                              data-copy-feedback="{{ __('customer_portal.direct.copied') }}"><span>{{ __('customer_portal.direct.copy') }}</span></x-button>
                </div>
                <p class="mt-2 text-sm">{{ __('customer_portal.direct.password_hint') }}</p>
            </div>
        @endif
        @if ($previewState !== null)
            <div role="status" class="flex flex-wrap items-center justify-between gap-2 border-b border-base-300 bg-info/10 px-4 py-2 text-sm">
                <span>{{ __('customer_portal.preview.running', ['name' => $previewName, 'until' => $portalPreview->expiresAt($previewState)->ftime()]) }}</span>
                <form method="POST" action="{{ route('customer.preview.stop') }}" class="inline">
                    @csrf
                    <x-icon-btn icon="visibility_off" tone="ghost" size="sm" type="submit" show-label>{{ __('customer_portal.preview.stop') }}</x-icon-btn>
                </form>
            </div>
        @endif
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
                            @case(\App\Services\CustomerPortal\PortalAccessService::STATE_PROVISIONAL)
                                <x-status-badge tone="warning" size="xs">{{ __('customer_portal.direct.state_provisional') }}</x-status-badge>
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
                            <span class="text-muted">—</span>
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
                            @if ($canPreview)
                                <form method="POST" action="{{ route('customers.portal-preview.start', [$customer, $portalUser]) }}" target="_blank" class="inline">
                                    @csrf
                                    <x-icon-btn icon="visibility" type="submit" :label="__('customer_portal.preview.action_as')" />
                                </form>
                            @endif
                            @if ($state === \App\Services\CustomerPortal\PortalAccessService::STATE_INVITED || $state === \App\Services\CustomerPortal\PortalAccessService::STATE_EXPIRED)
                                <form method="POST" action="{{ route('customers.portal-access.resend', [$customer, $portalUser]) }}" class="inline">
                                    @csrf
                                    <x-icon-btn icon="forward_to_inbox" type="submit" :label="__('Einladung erneut senden')" />
                                </form>
                            @elseif ($state === \App\Services\CustomerPortal\PortalAccessService::STATE_ACTIVE)
                                <form method="POST" action="{{ route('customers.portal-access.reset', [$customer, $portalUser]) }}"
                                      data-confirm-dialog
                                      data-confirm-title="{{ __('customer_portal.reset.confirm_title') }}"
                                      data-confirm-message="{{ __('customer_portal.reset.confirm_message', ['email' => $portalUser->email]) }}"
                                      data-confirm-tone="warning"
                                      data-confirm-label="{{ __('customer_portal.reset.confirm_label') }}"
                                      class="inline">
                                    @csrf
                                    <x-icon-btn icon="lock_reset" type="submit" :label="__('customer_portal.reset.action')" />
                                </form>
                            @endif
                            <form method="POST" action="{{ route('customers.portal-access.start-password', [$customer, $portalUser]) }}"
                                  data-confirm-dialog
                                  data-confirm-title="{{ __('customer_portal.direct.reissue_confirm_title') }}"
                                  data-confirm-message="{{ __('customer_portal.direct.reissue_confirm_message', ['email' => $portalUser->email]) }}"
                                  data-confirm-tone="warning"
                                  data-confirm-label="{{ __('customer_portal.direct.reissue_confirm_label') }}"
                                  class="inline">
                                @csrf
                                <x-icon-btn icon="key" type="submit" :label="__('customer_portal.direct.reissue_action')" />
                            </form>
                            @if ($portalUser->hasTwoFactorEnabled())
                                <form method="POST" action="{{ route('customers.portal-access.reset-second-factor', [$customer, $portalUser]) }}"
                                      data-confirm-dialog
                                      data-confirm-title="{{ __('customer_portal.second_factor.confirm_title') }}"
                                      data-confirm-message="{{ __('customer_portal.second_factor.confirm_message', ['email' => $portalUser->email]) }}"
                                      data-confirm-tone="warning"
                                      data-confirm-label="{{ __('customer_portal.second_factor.confirm_label') }}"
                                      class="inline">
                                    @csrf
                                    <x-icon-btn icon="phonelink_lock" type="submit" :label="__('customer_portal.second_factor.action')" />
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
