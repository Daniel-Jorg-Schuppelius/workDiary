{{--
  Created on   : Wed Jul 08 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@extends('layouts.app')
@section('title', __('cti.title'))
@section('nav-title', __('cti.title'))

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
            <h1 class="mb-1 font-['Space_Grotesk'] text-lg font-semibold">{{ __('cti.title') }}</h1>
            <p class="text-sm text-base-content/60">{{ __('cti.intro') }}</p>
        </div>

        {{-- Einmalige Webhook-URL nach Ausstellung --}}
        @if ($issuedUrl)
            <div class="rounded-box border border-warning/40 bg-warning/10 p-4">
                <div class="mb-1 text-sm font-semibold">{{ __('cti.new_heading') }}</div>
                <p class="mb-2 text-xs text-base-content/60">{{ __('cti.new_hint') }}</p>
                <code class="block break-all rounded bg-base-100 px-3 py-2 text-sm">{{ $issuedUrl }}</code>
            </div>
        @endif

        {{-- Anbindung ausstellen --}}
        <form method="POST" action="{{ route('admin.cti.connection.store') }}"
              class="rounded-box border border-base-300 bg-base-100 p-4 shadow-xs">
            @csrf
            <h2 class="mb-2 font-['Space_Grotesk'] text-base font-semibold">{{ __('cti.issue_heading') }}</h2>
            <div class="flex flex-wrap items-end gap-2">
                <label class="form-control grow">
                    <span class="label-text">{{ __('cti.field.name') }}</span>
                    <input type="text" name="name" value="{{ old('name') }}" placeholder="{{ __('cti.field.name_placeholder') }}" class="input input-bordered input-sm" required>
                </label>
                <label class="form-control">
                    <span class="label-text">{{ __('cti.field.provider') }}</span>
                    <select name="provider" class="select select-bordered select-sm">
                        @foreach ($providers as $provider)
                            <option value="{{ $provider }}" @selected(old('provider', 'sipgate') === $provider)>{{ ucfirst($provider) }}</option>
                        @endforeach
                    </select>
                </label>
                <button type="submit" class="btn btn-sm btn-primary">{{ __('cti.action.issue') }}</button>
            </div>
        </form>

        {{-- Anbindungen --}}
        <div class="rounded-box border border-base-300 bg-base-100 p-4 shadow-xs">
            <h2 class="mb-2 font-['Space_Grotesk'] text-base font-semibold">{{ __('cti.connections_heading') }}</h2>
            @if ($connections->isEmpty())
                <p class="text-sm text-base-content/60">{{ __('cti.no_connections') }}</p>
            @else
                <div class="overflow-x-auto">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>{{ __('cti.field.name') }}</th>
                                <th>{{ __('cti.field.provider') }}</th>
                                <th>{{ __('cti.col.status') }}</th>
                                <th>{{ __('cti.col.last_event') }}</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($connections as $connection)
                                <tr>
                                    <td>{{ $connection->name }}</td>
                                    <td class="text-base-content/60">{{ ucfirst($connection->provider) }}</td>
                                    <td>
                                        @if ($connection->isActive())
                                            <span class="badge badge-success badge-sm">{{ __('cti.status.active') }}</span>
                                        @else
                                            <span class="badge badge-ghost badge-sm">{{ __('cti.status.inactive') }}</span>
                                        @endif
                                    </td>
                                    <td class="text-base-content/60">{{ $connection->last_event_at?->diffForHumans() ?? '—' }}</td>
                                    <td class="text-right">
                                        @if ($connection->isActive())
                                            <form method="POST" action="{{ route('admin.cti.disconnect') }}">
                                                @csrf
                                                <input type="hidden" name="connection" value="{{ $connection->sqid }}">
                                                <button type="submit" class="btn btn-ghost btn-xs text-error">{{ __('cti.action.disconnect') }}</button>
                                            </form>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
</x-page-shell>
@endsection
