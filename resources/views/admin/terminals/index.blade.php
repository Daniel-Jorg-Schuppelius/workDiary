{{--
  Created on   : Wed Jul 08 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@extends('layouts.app')
@section('title', __('terminal.title'))
@section('nav-title', __('terminal.title'))

@section('content')
<x-page-shell>
    <div class="space-y-4">
        <x-validation-errors first />

        <x-page-toolbar :subtitle="__('terminal.intro')" />

        {{-- Einmalige Ingest-URL --}}
        @if ($issuedUrl)
            <div class="rounded-box border border-warning/40 bg-warning/10 p-4">
                <div class="mb-1 text-sm font-semibold">{{ __('terminal.new_heading') }}</div>
                <p class="mb-2 text-xs text-muted">{{ __('terminal.new_hint') }}</p>
                <code class="block break-all rounded bg-base-100 px-3 py-2 text-sm">{{ $issuedUrl }}</code>
                @if ($issuedKioskUrl)
                    {{-- Kiosk-Modus (MVP-800): dasselbe Gerätetoken, im Browser eines Tablets geöffnet. --}}
                    <div class="mb-1 mt-3 text-sm font-semibold">{{ __('terminal.kiosk.heading') }}</div>
                    <p class="mb-2 text-xs text-muted">{{ __('terminal.kiosk.hint') }}</p>
                    <code class="block break-all rounded bg-base-100 px-3 py-2 text-sm">{{ $issuedKioskUrl }}</code>
                @endif
            </div>
        @endif

        {{-- Terminals --}}
        <x-card :title="__('terminal.terminals_heading')">
            <x-slot:actions>
                <x-icon-btn icon="add" tone="primary" size="sm"
                            data-entry-modal-trigger
                            :href="route('admin.terminals.create')"
                            show-label>{{ __('terminal.action.register') }}</x-icon-btn>
            </x-slot:actions>
            @if ($terminals->isEmpty())
                <p class="text-sm text-muted">{{ __('terminal.no_terminals') }}</p>
            @else
                <x-table :bare="true">
                    <x-slot:head>
                            <tr>
                                <th>{{ __('terminal.field.name') }}</th>
                                <th>{{ __('terminal.field.site') }}</th>
                                <th>{{ __('terminal.col.status') }}</th>
                                <th>{{ __('terminal.col.status_display') }}</th>
                                <th>{{ __('terminal.col.last_seen') }}</th>
                                <th></th>
                            </tr>
                    </x-slot:head>
                            @foreach ($terminals as $terminal)
                                <tr>
                                    <td>{{ $terminal->name }}</td>
                                    <td class="text-muted">{{ $terminal->site?->name ?? '—' }}</td>
                                    <td>
                                        @if ($terminal->isActive())
                                            <span class="badge badge-success badge-sm">{{ __('terminal.status.active') }}</span>
                                        @else
                                            <span class="badge badge-ghost badge-sm">{{ __('terminal.status.inactive') }}</span>
                                        @endif
                                    </td>
                                    <td>
                                        <form method="POST" action="{{ route('admin.terminals.toggle-status') }}">
                                            @csrf
                                            <input type="hidden" name="terminal" value="{{ $terminal->sqid }}">
                                            <button type="submit" class="btn btn-ghost btn-xs" title="{{ __('terminal.status_display.help') }}">
                                                @if ($terminal->show_status)
                                                    <span class="badge badge-info badge-sm">{{ __('terminal.status_display.on') }}</span>
                                                @else
                                                    <span class="badge badge-ghost badge-sm">{{ __('terminal.status_display.off') }}</span>
                                                @endif
                                            </button>
                                        </form>
                                    </td>
                                    <td class="text-muted">
                                        {{ $terminal->last_seen_at?->diffForHumans() ?? '—' }}
                                        @if (($terminal->last_buffer_size ?? 0) > 0)
                                            <span class="badge badge-warning badge-sm" title="{{ __('terminal.buffer.help') }}">{{ __('terminal.buffer.label') }}: {{ $terminal->last_buffer_size }}</span>
                                        @endif
                                    </td>
                                    <td class="text-right whitespace-nowrap">
                                        @if ($terminal->isActive())
                                            <form method="POST" action="{{ route('admin.terminals.rotate') }}" class="inline">
                                                @csrf
                                                <input type="hidden" name="terminal" value="{{ $terminal->sqid }}">
                                                <button type="submit" class="btn btn-ghost btn-xs" title="{{ __('terminal.action.rotate_help') }}">{{ __('terminal.action.rotate') }}</button>
                                            </form>
                                            <form method="POST" action="{{ route('admin.terminals.disconnect') }}" class="inline">
                                                @csrf
                                                <input type="hidden" name="terminal" value="{{ $terminal->sqid }}">
                                                <button type="submit" class="btn btn-ghost btn-xs text-error">{{ __('terminal.action.disable') }}</button>
                                            </form>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                </x-table>
            @endif
        </x-card>

        {{-- Badges --}}
        <x-card :title="__('terminal.badges_heading')">
            <x-slot:actions>
                <x-icon-btn icon="add" tone="primary" size="sm"
                            data-entry-modal-trigger
                            :href="route('admin.terminals.badges.create')"
                            show-label>{{ __('terminal.action.assign') }}</x-icon-btn>
            </x-slot:actions>
            @if ($badges->isEmpty())
                <p class="text-sm text-muted">{{ __('terminal.no_badges') }}</p>
            @else
                <x-table :bare="true">
                    <x-slot:head>
                            <tr>
                                <th>{{ __('terminal.badge.user') }}</th>
                                <th>{{ __('terminal.badge.label') }}</th>
                                <th>{{ __('terminal.badge.validity') }}</th>
                                <th>{{ __('terminal.col.status') }}</th>
                                <th></th>
                            </tr>
                    </x-slot:head>
                            @foreach ($badges as $badge)
                                <tr>
                                    <td>{{ $badge->user?->name ?? '—' }}</td>
                                    <td class="text-muted">{{ $badge->label ?? '—' }}</td>
                                    <td class="text-muted whitespace-nowrap">
                                        @if ($badge->valid_from || $badge->valid_until)
                                            {{ $badge->valid_from?->format('d.m.Y') ?? '…' }}–{{ $badge->valid_until?->format('d.m.Y') ?? '…' }}
                                            @unless ($badge->isUsableOn(now()))
                                                <span class="badge badge-warning badge-sm">{{ __('terminal.badge.outside_validity') }}</span>
                                            @endunless
                                        @else
                                            —
                                        @endif
                                    </td>
                                    <td>
                                        @if ($badge->isActive())
                                            <span class="badge badge-success badge-sm">{{ __('terminal.status.active') }}</span>
                                        @else
                                            <span class="badge badge-error badge-sm">{{ __('terminal.status.revoked') }}</span>
                                        @endif
                                    </td>
                                    <td class="text-right">
                                        @if ($badge->isActive())
                                            <form method="POST" action="{{ route('admin.terminals.badges.revoke') }}">
                                                @csrf
                                                <input type="hidden" name="badge" value="{{ $badge->sqid }}">
                                                <button type="submit" class="btn btn-ghost btn-xs text-error">{{ __('terminal.action.revoke') }}</button>
                                            </form>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                </x-table>
            @endif
        </x-card>
        {{-- Terminal-PINs (MVP-803) --}}
        <x-card :title="__('terminal.pin.heading')">
            <x-slot:actions>
                <x-icon-btn icon="add" tone="primary" size="sm"
                            data-entry-modal-trigger
                            :href="route('admin.terminals.pins.create')"
                            show-label>{{ __('terminal.pin.action.set') }}</x-icon-btn>
            </x-slot:actions>
            <p class="mb-3 text-sm text-muted">{{ __('terminal.pin.intro') }}</p>
            @if ($pins->isEmpty())
                <p class="text-sm text-muted">{{ __('terminal.pin.empty') }}</p>
            @else
                <x-table :bare="true">
                    <x-slot:head>
                        <tr>
                            <th>{{ __('terminal.badge.user') }}</th>
                            <th>{{ __('terminal.pin.field.personnel_number') }}</th>
                            <th>{{ __('terminal.col.status') }}</th>
                            <th></th>
                        </tr>
                    </x-slot:head>
                    @foreach ($pins as $pin)
                        <tr>
                            <td>{{ $pin->user?->name ?? '—' }}</td>
                            <td class="font-mono text-xs">{{ $pin->user?->personnel_number ?? '—' }}</td>
                            <td>
                                @if ($pin->isLocked())
                                    <span class="badge badge-error badge-sm">{{ __('terminal.pin.status.locked_until', ['time' => $pin->locked_until?->format('H:i')]) }}</span>
                                @else
                                    <span class="badge badge-success badge-sm">{{ __('terminal.status.active') }}</span>
                                @endif
                            </td>
                            <td class="text-right whitespace-nowrap">
                                @if ($pin->isLocked())
                                    <form method="POST" action="{{ route('admin.terminals.pins.unlock') }}" class="inline">
                                        @csrf
                                        <input type="hidden" name="pin" value="{{ $pin->sqid }}">
                                        <button type="submit" class="btn btn-ghost btn-xs">{{ __('terminal.pin.action.unlock') }}</button>
                                    </form>
                                @endif
                                <x-action-form :action="route('admin.terminals.pins.remove')" class="inline"
                                               :confirm="__('terminal.pin.confirm.remove')" :confirm-label="__('terminal.pin.action.remove')" confirm-tone="error">
                                    <input type="hidden" name="pin" value="{{ $pin->sqid }}">
                                    <button type="submit" class="btn btn-ghost btn-xs text-error">{{ __('terminal.pin.action.remove') }}</button>
                                </x-action-form>
                            </td>
                        </tr>
                    @endforeach
                </x-table>
            @endif
        </x-card>
        {{-- Check-in-Punkte (MVP-800) --}}
        <x-card :title="__('terminal.checkpoint.heading')">
            <x-slot:actions>
                <x-icon-btn icon="add" tone="primary" size="sm"
                            data-entry-modal-trigger
                            :href="route('admin.terminals.checkpoints.create')"
                            show-label>{{ __('terminal.checkpoint.action.create') }}</x-icon-btn>
            </x-slot:actions>
            <p class="mb-3 text-sm text-muted">{{ __('terminal.checkpoint.intro') }}</p>
            @if ($checkpoints->isEmpty())
                <p class="text-sm text-muted">{{ __('terminal.checkpoint.empty') }}</p>
            @else
                <x-table :bare="true">
                    <x-slot:head>
                        <tr>
                            <th>{{ __('terminal.field.name') }}</th>
                            <th>{{ __('terminal.checkpoint.field.kind') }}</th>
                            <th>{{ __('terminal.checkpoint.field.location') }}</th>
                            <th>{{ __('terminal.checkpoint.field.radius') }}</th>
                            <th>{{ __('terminal.col.status') }}</th>
                            <th></th>
                        </tr>
                    </x-slot:head>
                    @foreach ($checkpoints as $checkpoint)
                        <tr>
                            <td>{{ $checkpoint->name }}</td>
                            <td>{{ $checkpoint->kind->label() }}</td>
                            <td class="text-muted">{{ $checkpoint->vehicle?->displayName() ?? $checkpoint->site?->name ?? '—' }}</td>
                            <td class="text-muted">{{ $checkpoint->radius_m !== null ? $checkpoint->radius_m . ' m' : '—' }}</td>
                            <td>
                                @if ($checkpoint->active)
                                    <span class="badge badge-success badge-sm">{{ __('terminal.status.active') }}</span>
                                @else
                                    <span class="badge badge-ghost badge-sm">{{ __('terminal.status.inactive') }}</span>
                                @endif
                            </td>
                            <td class="text-right whitespace-nowrap">
                                <x-icon-btn icon="qr_code_2" :href="route('admin.terminals.checkpoints.qr', $checkpoint->sqid)" :label="__('terminal.checkpoint.action.qr')" />
                                <form method="POST" action="{{ route('admin.terminals.checkpoints.toggle') }}" class="inline">
                                    @csrf
                                    <input type="hidden" name="checkpoint" value="{{ $checkpoint->sqid }}">
                                    <button type="submit" class="btn btn-ghost btn-xs {{ $checkpoint->active ? 'text-error' : '' }}">
                                        {{ $checkpoint->active ? __('terminal.action.disable') : __('terminal.checkpoint.action.enable') }}
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </x-table>
            @endif
        </x-card>
    </div>
</x-page-shell>
@endsection
