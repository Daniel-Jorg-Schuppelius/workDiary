{{--
  Created on   : Thu Nov 12 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Anmelderichtlinie der Organisation (Feature 205): Zeiten, Netze, Passkey-Pflicht, Gerätefreigaben, Notaus. --}}
@extends('layouts.app')

@section('title', __('login_policy.title.index') . ' — ' . config('app.name', 'WorkDiary'))
@section('nav-title', __('login_policy.title.index'))

@php
    /** @var \App\Models\Auth\OrganizationLoginPolicy|null $policy */
    $locked = $policy?->isLockedDown() === true;
    $hours = $policy?->hoursByWeekday() ?? [];
    $monday = \Carbon\CarbonImmutable::now()->startOfWeek(\Carbon\CarbonInterface::MONDAY);
    $modeTone = static fn (?\App\Enums\Auth\LoginPolicyMode $mode): string => match ($mode) {
        \App\Enums\Auth\LoginPolicyMode::Enforce => 'success',
        \App\Enums\Auth\LoginPolicyMode::Log => 'warning',
        default => 'ghost',
    };
    $roleList = static fn (array $roles): string => $roles === [] ? '' : implode(', ', array_map(static fn (string $role): string => $roleLabels[$role] ?? $role, $roles));
    $fmt = static fn ($dt) => $dt instanceof \Carbon\CarbonInterface ? \App\Support\Tz::toLocal($dt)?->translatedFormat('d.m.Y H:i') : '—';
@endphp

@section('content')
<x-index-page
    :subtitle="__('login_policy.subtitle')"
    :badge="$locked ? __('login_policy.badge.locked') : null"
    badge-tone="error"
>
    <x-slot:actions>
        <x-help-button topic="admin.login-policy" />
        @if ($canManage)
            <x-icon-btn icon="edit" tone="primary" size="sm" show-label data-entry-modal-trigger
                        :href="route('admin.login-policy.edit')">{{ __('login_policy.action.edit') }}</x-icon-btn>
        @endif
    </x-slot:actions>

    <x-validation-errors first />

    {{-- ── Notaus (MVP-1657) ─────────────────────────────────────────── --}}
    <x-card @class(['border-error/50 bg-error/5' => $locked])>
        <h2 class="flex items-center gap-2 font-['Space_Grotesk'] text-base font-semibold">
            <x-icon name="emergency_home" /> {{ __('login_policy.section.lockdown') }}
        </h2>
        @if ($locked)
            <p class="mt-1 text-sm">
                {{ __('login_policy.lockdown.active_since', ['date' => $fmt($policy->lockdown_at), 'name' => $policy->lockdownActor?->name ?? '—']) }}
            </p>
            <p class="mt-1 text-sm"><span class="text-muted">{{ __('login_policy.field.reason') }}:</span> {{ $policy->lockdown_reason }}</p>
            @if ($canLockdown)
                <form method="POST" action="{{ route('admin.login-policy.lockdown.destroy') }}" class="mt-3 flex flex-wrap items-end gap-2">
                    @csrf @method('DELETE')
                    @unless (\App\Support\Auth\RecentAuthentication::isRecent(request()))
                        <input type="password" name="current_password" autocomplete="current-password" required
                               aria-label="{{ __('login_policy.field.password') }}" placeholder="{{ __('login_policy.field.password') }}"
                               class="input input-sm input-bordered">
                    @endunless
                    <x-icon-btn icon="lock_open" tone="success" size="sm" type="submit" show-label>{{ __('login_policy.action.lift') }}</x-icon-btn>
                </form>
            @endif
        @else
            <p class="mt-1 text-sm text-muted">{{ __('login_policy.lockdown.description') }}</p>
            @if ($canLockdown && (int) auth()->user()?->organization_id === (int) $organization->id)
                <div class="mt-3">
                    <x-icon-btn icon="emergency_home" tone="error" size="sm" show-label data-entry-modal-trigger
                                :href="route('admin.login-policy.lockdown.create')">{{ __('login_policy.action.engage') }}</x-icon-btn>
                </div>
            @endif
        @endif
    </x-card>

    <div class="grid gap-4 lg:grid-cols-3">
        {{-- ── Anmeldezeiten (MVP-1654) ─────────────────────────────── --}}
        <x-card>
            <h2 class="flex items-center gap-2 font-['Space_Grotesk'] text-base font-semibold">
                <x-icon name="schedule" /> {{ __('login_policy.section.hours') }}
                <x-status-badge size="sm" :tone="$modeTone($policy?->hours_mode)">{{ ($policy?->hours_mode ?? \App\Enums\Auth\LoginPolicyMode::Off)->label() }}</x-status-badge>
            </h2>
            <ul class="mt-2 space-y-1 text-sm">
                @for ($day = 1; $day <= 7; $day++)
                    <li class="flex justify-between gap-2">
                        <span>{{ $monday->addDays($day - 1)->translatedFormat('l') }}</span>
                        <span class="font-mono">{{ isset($hours[$day]) ? $hours[$day]['from'] . '–' . $hours[$day]['until'] : __('login_policy.hours.closed') }}</span>
                    </li>
                @endfor
            </ul>
            <p class="mt-2 text-xs text-muted">
                {{ __('login_policy.hours.applies_to') }}:
                {{ $roleList($policy?->hourRoleNames() ?? []) ?: __('login_policy.hours.all_roles') }}
                · {{ __('login_policy.hours.timezone', ['tz' => \App\Support\Tz::ofOrganization($organization)]) }}
            </p>
        </x-card>

        {{-- ── Netzbereiche (MVP-1655) ─────────────────────────────── --}}
        <x-card>
            <h2 class="flex items-center gap-2 font-['Space_Grotesk'] text-base font-semibold">
                <x-icon name="lan" /> {{ __('login_policy.section.networks') }}
                <x-status-badge size="sm" :tone="$modeTone($policy?->network_mode)">{{ ($policy?->network_mode ?? \App\Enums\Auth\LoginPolicyMode::Off)->label() }}</x-status-badge>
            </h2>
            <ul class="mt-2 space-y-1 text-sm">
                @forelse ($policy?->networkEntries() ?? [] as $entry)
                    <li>
                        <span class="font-mono">{{ $entry }}</span>
                        @if (\App\Services\Auth\LoginPolicy\LoginNetworks::isHostname($entry))
                            {{-- Nur öffentliche Adressen anzeigen; interne nur als Anzahl (Sicherheitsprüfung M3). --}}
                            @php $hostInfo = $resolvedHosts[$entry] ?? null; @endphp
                            <span class="text-xs text-muted">→
                                @if ($hostInfo === null)
                                    {{ __('login_policy.networks.unresolved') }}
                                @else
                                    {{ implode(', ', $hostInfo['public']) }}
                                    @if ($hostInfo['internal'] > 0)
                                        {{ __('login_policy.networks.internal', ['count' => $hostInfo['internal']]) }}
                                    @endif
                                @endif
                            </span>
                        @endif
                    </li>
                @empty
                    <li class="text-muted">{{ __('login_policy.networks.none') }}</li>
                @endforelse
            </ul>
            @if ($clientIpInternal)
                {{-- Eigene Adresse privat/Loopback: der Proxy reicht die Client-Adresse nicht durch (N8). --}}
                <div class="alert alert-warning mt-2 bg-warning/10 border-warning/30 text-xs" role="note">
                    <x-icon name="warning" />
                    <span>{{ __('login_policy.networks.client_internal') }}</span>
                </div>
            @endif
            <p class="mt-2 text-xs text-muted">
                {{ __('login_policy.networks.client', ['ip' => $clientIp ?? '—']) }}
                @if ($policy !== null && $policy->network_mode->isActive())
                    · {{ $clientInNetworks ? __('login_policy.networks.client_inside') : __('login_policy.networks.client_outside') }}
                @endif
                @if ($resolvedAt)
                    · {{ __('login_policy.networks.resolved_at', ['date' => $fmt(\Carbon\CarbonImmutable::createFromTimestamp($resolvedAt))]) }}
                @endif
            </p>
        </x-card>

        {{-- ── Passkey-Pflicht (MVP-1656) ──────────────────────────── --}}
        <x-card>
            <h2 class="flex items-center gap-2 font-['Space_Grotesk'] text-base font-semibold">
                <x-icon name="key" /> {{ __('login_policy.section.passkey') }}
            </h2>
            <p class="mt-2 text-sm">{{ $roleList($policy?->passkeyRoleNames() ?? []) ?: __('login_policy.passkey.none') }}</p>
            <p class="mt-2 text-xs text-muted">{{ __('login_policy.hint.passkey') }}</p>
        </x-card>
    </div>

    @if ($canManage)
        {{-- ── Freigabeanfragen und freigegebene Geräte (MVP-1655) ──── --}}
        <x-card>
            <h2 class="flex items-center gap-2 font-['Space_Grotesk'] text-base font-semibold">
                <x-icon name="devices" /> {{ __('login_policy.section.devices') }}
            </h2>
            @if ($pendingDevices->isEmpty() && $approvedDevices->isEmpty() && $rejectedDevices->isEmpty())
                <x-empty-state compact icon="devices" :title="__('login_policy.devices.none')" />
            @else
                <x-table bare>
                    <x-slot:head>
                        <tr>
                            <th>{{ __('login_policy.col.person') }}</th>
                            <th>{{ __('login_policy.col.device') }}</th>
                            <th>{{ __('login_policy.col.status') }}</th>
                            <th>{{ __('login_policy.col.last_seen') }}</th>
                            <th class="text-right">{{ __('login_policy.col.action') }}</th>
                        </tr>
                    </x-slot:head>
                    @foreach ($pendingDevices->concat($approvedDevices)->concat($rejectedDevices) as $device)
                        @php $sqid = \App\Support\Sqid::encode(\App\Models\Auth\UserKnownDevice::class, (int) $device->id); @endphp
                        <tr>
                            <td>{{ $device->user?->name }}</td>
                            <td>{{ $device->label }}</td>
                            <td>
                                @if ($device->approved_at)
                                    <x-status-badge tone="success" size="sm">{{ __('login_policy.devices.approved', ['date' => $fmt($device->approved_at), 'name' => $device->approver?->name ?? '—']) }}</x-status-badge>
                                @elseif ($device->rejected_at)
                                    <x-status-badge tone="error" size="sm">{{ __('login_policy.devices.rejected', ['date' => $fmt($device->rejected_at), 'name' => $device->rejecter?->name ?? '—']) }}</x-status-badge>
                                @else
                                    <x-status-badge tone="warning" size="sm">{{ __('login_policy.devices.pending', ['date' => $fmt($device->approval_requested_at)]) }}</x-status-badge>
                                @endif
                            </td>
                            <td class="text-xs">{{ $fmt($device->last_seen_at) }}</td>
                            <td class="text-right">
                                <div class="flex justify-end gap-1">
                                    @unless ($device->approved_at)
                                        <x-action-form :action="route('admin.login-policy.devices.approve', ['deviceSqid' => $sqid])">
                                            <x-icon-btn icon="check_circle" tone="success" size="sm" type="submit" :label="__('login_policy.action.approve')" />
                                        </x-action-form>
                                    @endunless
                                    @if ($device->rejected_at)
                                        {{-- Vergessen: die nächste Anmeldung von diesem Gerät darf wieder anfragen. --}}
                                        <x-action-form :action="route('admin.login-policy.devices.destroy', ['deviceSqid' => $sqid])" method="DELETE"
                                                       :confirm="__('login_policy.devices.confirm_forget')"
                                                       confirm-icon="delete" confirm-tone="warning"
                                                       :confirm-label="__('login_policy.action.forget')">
                                            <x-icon-btn icon="delete" size="sm" type="submit" :label="__('login_policy.action.forget')" />
                                        </x-action-form>
                                    @else
                                        <x-action-form :action="route('admin.login-policy.devices.reject', ['deviceSqid' => $sqid])"
                                                       :confirm="$device->approved_at ? __('login_policy.devices.confirm_revoke') : __('login_policy.devices.confirm_reject')"
                                                       confirm-icon="block" confirm-tone="error"
                                                       :confirm-label="$device->approved_at ? __('login_policy.action.revoke') : __('login_policy.action.reject')">
                                            <x-icon-btn icon="block" tone="error" size="sm" type="submit"
                                                        :label="$device->approved_at ? __('login_policy.action.revoke') : __('login_policy.action.reject')" />
                                        </x-action-form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </x-table>
            @endif
        </x-card>

        {{-- ── Protokoll der letzten sieben Tage ───────────────────── --}}
        <x-card>
            <h2 class="flex items-center gap-2 font-['Space_Grotesk'] text-base font-semibold">
                <x-icon name="policy" /> {{ __('login_policy.section.log') }}
            </h2>
            <p class="mt-1 text-xs text-muted">{{ __('login_policy.hint.log') }}</p>
            @if ($events->isEmpty())
                <x-empty-state compact icon="policy" :title="__('login_policy.log.none')" />
            @else
                <x-table bare>
                    <x-slot:head>
                        <tr>
                            <th>{{ __('login_policy.col.when') }}</th>
                            <th>{{ __('login_policy.col.person') }}</th>
                            <th>{{ __('login_policy.col.reason') }}</th>
                            <th>{{ __('login_policy.col.effect') }}</th>
                            <th>{{ __('login_policy.col.ip') }}</th>
                        </tr>
                    </x-slot:head>
                    @foreach ($events as $event)
                        @php $reason = \App\Enums\Auth\LoginPolicyReason::tryFrom((string) ($event->meta['reason'] ?? '')); @endphp
                        <tr>
                            <td class="text-xs">{{ $fmt($event->occurred_at) }}</td>
                            <td>{{ $event->user?->name ?? ($event->meta['user'] ?? '—') }}</td>
                            <td>{{ $reason?->label() ?? '—' }}</td>
                            <td>
                                @if ($event->event === \App\Enums\Security\SecurityEventType::LoginPolicyLogged)
                                    <x-status-badge tone="warning" size="sm">{{ __('login_policy.log.would_deny') }}</x-status-badge>
                                @else
                                    <x-status-badge tone="error" size="sm">{{ __('login_policy.log.denied') }}</x-status-badge>
                                @endif
                            </td>
                            {{-- Gekürzt auf /24 bzw. /48 (Sicherheitsprüfung N9). --}}
                            <td class="font-mono text-xs">{{ \App\Services\Auth\LoginPolicy\LoginNetworks::masked($event->ip?->getValue()) ?? '—' }}</td>
                        </tr>
                    @endforeach
                </x-table>
            @endif
        </x-card>
    @endif
</x-index-page>
@endsection
