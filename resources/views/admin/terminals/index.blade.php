@extends('layouts.app')
@section('title', __('terminal.title'))
@section('nav-title', __('terminal.title'))

@section('content')
<x-page-shell>
    <div class="space-y-4">
        @if (session('success'))
            <div class="alert alert-success text-sm">{{ session('success') }}</div>
        @endif
        @if (session('error'))
            <div class="alert alert-error text-sm">{{ session('error') }}</div>
        @endif
        <x-validation-errors first />

        <div class="rounded-box border border-base-300 bg-base-100 p-4 shadow-xs">
            <h1 class="mb-1 font-['Space_Grotesk'] text-lg font-semibold">{{ __('terminal.title') }}</h1>
            <p class="text-sm text-base-content/60">{{ __('terminal.intro') }}</p>
        </div>

        {{-- Einmalige Ingest-URL --}}
        @if ($issuedUrl)
            <div class="rounded-box border border-warning/40 bg-warning/10 p-4">
                <div class="mb-1 text-sm font-semibold">{{ __('terminal.new_heading') }}</div>
                <p class="mb-2 text-xs text-base-content/60">{{ __('terminal.new_hint') }}</p>
                <code class="block break-all rounded bg-base-100 px-3 py-2 text-sm">{{ $issuedUrl }}</code>
            </div>
        @endif

        {{-- Terminals --}}
        <div class="rounded-box border border-base-300 bg-base-100 p-4 shadow-xs">
            <h2 class="mb-2 font-['Space_Grotesk'] text-base font-semibold">{{ __('terminal.terminals_heading') }}</h2>
            @if ($terminals->isEmpty())
                <p class="mb-3 text-sm text-base-content/60">{{ __('terminal.no_terminals') }}</p>
            @else
                <x-table class="mb-3">
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
                                    <td class="text-base-content/60">{{ $terminal->site?->name ?? '—' }}</td>
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
                                    <td class="text-base-content/60">
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

            <form method="POST" action="{{ route('admin.terminals.store') }}" class="flex flex-wrap items-end gap-2">
                @csrf
                <label class="form-control grow">
                    <span class="label-text">{{ __('terminal.field.name') }}</span>
                    <input type="text" name="name" value="{{ old('name') }}" placeholder="{{ __('terminal.field.name_placeholder') }}" class="input input-bordered input-sm" required>
                </label>
                <label class="form-control">
                    <span class="label-text">{{ __('terminal.field.site') }}</span>
                    <select name="site" class="select select-bordered select-sm">
                        <option value="">{{ __('terminal.field.no_site') }}</option>
                        @foreach ($sites as $site)
                            <option value="{{ $site['sqid'] }}">{{ $site['name'] }}</option>
                        @endforeach
                    </select>
                </label>
                <button type="submit" class="btn btn-sm btn-primary">{{ __('terminal.action.register') }}</button>
            </form>
        </div>

        {{-- Badges --}}
        <div class="rounded-box border border-base-300 bg-base-100 p-4 shadow-xs">
            <h2 class="mb-2 font-['Space_Grotesk'] text-base font-semibold">{{ __('terminal.badges_heading') }}</h2>
            @if ($badges->isEmpty())
                <p class="mb-3 text-sm text-base-content/60">{{ __('terminal.no_badges') }}</p>
            @else
                <x-table class="mb-3">
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
                                    <td class="text-base-content/60">{{ $badge->label ?? '—' }}</td>
                                    <td class="text-base-content/60 whitespace-nowrap">
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

            <form method="POST" action="{{ route('admin.terminals.badges.store') }}" class="flex flex-wrap items-end gap-2">
                @csrf
                <label class="form-control">
                    <span class="label-text">{{ __('terminal.badge.user') }}</span>
                    <select name="user" class="select select-bordered select-sm" required>
                        @foreach ($users as $user)
                            <option value="{{ $user['sqid'] }}">{{ $user['name'] }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="form-control grow">
                    <span class="label-text">{{ __('terminal.badge.uid') }}</span>
                    <input type="text" name="badge_uid" value="{{ old('badge_uid') }}" placeholder="{{ __('terminal.badge.uid_placeholder') }}" class="input input-bordered input-sm" required>
                    <span class="label-text-alt text-base-content/50">{{ __('terminal.badge.uid_help') }}</span>
                </label>
                <label class="form-control">
                    <span class="label-text">{{ __('terminal.badge.label') }}</span>
                    <input type="text" name="label" value="{{ old('label') }}" class="input input-bordered input-sm">
                </label>
                <label class="form-control">
                    <span class="label-text">{{ __('terminal.badge.valid_from') }}</span>
                    <input type="date" name="valid_from" value="{{ old('valid_from') }}" class="input input-bordered input-sm">
                </label>
                <label class="form-control">
                    <span class="label-text">{{ __('terminal.badge.valid_until') }}</span>
                    <input type="date" name="valid_until" value="{{ old('valid_until') }}" class="input input-bordered input-sm">
                </label>
                <button type="submit" class="btn btn-sm btn-primary">{{ __('terminal.action.assign') }}</button>
            </form>
        </div>
    </div>
</x-page-shell>
@endsection
