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
        @if ($errors->any())
            <div class="alert alert-error text-sm">{{ $errors->first() }}</div>
        @endif

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
                <div class="mb-3 overflow-x-auto">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>{{ __('terminal.field.name') }}</th>
                                <th>{{ __('terminal.field.site') }}</th>
                                <th>{{ __('terminal.col.status') }}</th>
                                <th>{{ __('terminal.col.last_seen') }}</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
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
                                    <td class="text-base-content/60">{{ $terminal->last_seen_at?->diffForHumans() ?? '—' }}</td>
                                    <td class="text-right">
                                        @if ($terminal->isActive())
                                            <form method="POST" action="{{ route('admin.terminals.disconnect') }}">
                                                @csrf
                                                <input type="hidden" name="terminal" value="{{ $terminal->sqid }}">
                                                <button type="submit" class="btn btn-ghost btn-xs text-error">{{ __('terminal.action.disable') }}</button>
                                            </form>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
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
                <div class="mb-3 overflow-x-auto">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>{{ __('terminal.badge.user') }}</th>
                                <th>{{ __('terminal.badge.label') }}</th>
                                <th>{{ __('terminal.col.status') }}</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($badges as $badge)
                                <tr>
                                    <td>{{ $badge->user?->name ?? '—' }}</td>
                                    <td class="text-base-content/60">{{ $badge->label ?? '—' }}</td>
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
                        </tbody>
                    </table>
                </div>
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
                <button type="submit" class="btn btn-sm btn-primary">{{ __('terminal.action.assign') }}</button>
            </form>
        </div>
    </div>
</x-page-shell>
@endsection
