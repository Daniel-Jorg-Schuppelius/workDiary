{{--
  Created on   : Wed Jul 08 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@extends('layouts.app')
@section('title', __('shipping.title'))
@section('nav-title', __('shipping.title'))

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
            <h1 class="mb-1 font-['Space_Grotesk'] text-lg font-semibold">{{ __('shipping.title') }}</h1>
            <p class="text-sm text-base-content/60">{{ __('shipping.intro') }}</p>
        </div>

        {{-- Anbindung anlegen/bearbeiten (je Carrier eine Anbindung, Upsert) --}}
        <form method="POST" action="{{ route('admin.shipments.connections.store') }}"
              class="rounded-box border border-base-300 bg-base-100 p-4 shadow-xs">
            @csrf
            <h2 class="mb-1 font-['Space_Grotesk'] text-base font-semibold">{{ __('shipping.form_heading') }}</h2>
            <p class="mb-3 text-xs text-base-content/60">{{ __('shipping.form_hint') }}</p>

            <div class="grid gap-3 sm:grid-cols-2">
                <label class="form-control">
                    <span class="label-text">{{ __('shipping.field.carrier') }}</span>
                    <select name="carrier" class="select select-bordered select-sm" required>
                        @foreach ($carriers as $carrier)
                            <option value="{{ $carrier }}" @selected(old('carrier') === $carrier)>{{ strtoupper($carrier) }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="form-control">
                    <span class="label-text">{{ __('shipping.field.name') }}</span>
                    <input type="text" name="name" value="{{ old('name') }}" class="input input-bordered input-sm" maxlength="120" required>
                </label>
                <label class="form-control">
                    <span class="label-text">{{ __('shipping.field.username') }}</span>
                    <input type="text" name="username" value="{{ old('username') }}" class="input input-bordered input-sm" autocomplete="off">
                </label>
                <label class="form-control">
                    <span class="label-text">{{ __('shipping.field.password') }}</span>
                    <input type="password" name="password" class="input input-bordered input-sm" autocomplete="new-password">
                </label>
                <label class="form-control">
                    <span class="label-text">{{ __('shipping.field.api_key') }}</span>
                    <input type="password" name="api_key" class="input input-bordered input-sm" autocomplete="off">
                </label>
                <label class="form-control">
                    <span class="label-text">{{ __('shipping.field.billing_number') }}</span>
                    <input type="text" name="billing_number" value="{{ old('billing_number') }}" class="input input-bordered input-sm" maxlength="60">
                </label>
            </div>

            <div class="mt-3 flex flex-wrap items-center gap-4">
                <label class="label cursor-pointer gap-2">
                    <input type="checkbox" name="sandbox" value="1" class="checkbox checkbox-sm" @checked(old('sandbox'))>
                    <span class="label-text">{{ __('shipping.field.sandbox') }}</span>
                </label>
                <label class="label cursor-pointer gap-2">
                    <input type="checkbox" name="active" value="1" class="checkbox checkbox-sm" @checked(old('active', true))>
                    <span class="label-text">{{ __('shipping.field.active') }}</span>
                </label>
                <button type="submit" class="btn btn-sm btn-primary ml-auto">{{ __('shipping.action.save') }}</button>
            </div>
            <p class="mt-2 text-xs text-base-content/50">{{ __('shipping.secret_hint') }}</p>
        </form>

        {{-- Bestehende Anbindungen --}}
        <div class="rounded-box border border-base-300 bg-base-100 p-4 shadow-xs">
            <h2 class="mb-2 font-['Space_Grotesk'] text-base font-semibold">{{ __('shipping.connections_heading') }}</h2>
            @if ($connections->isEmpty())
                <p class="text-sm text-base-content/60">{{ __('shipping.no_connections') }}</p>
            @else
                <div class="overflow-x-auto">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>{{ __('shipping.field.carrier') }}</th>
                                <th>{{ __('shipping.field.name') }}</th>
                                <th>{{ __('shipping.col.mode') }}</th>
                                <th>{{ __('shipping.col.status') }}</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($connections as $connection)
                                <tr>
                                    <td class="font-medium">{{ strtoupper($connection->carrier) }}</td>
                                    <td>{{ $connection->name }}</td>
                                    <td>
                                        @if ($connection->sandbox)
                                            <span class="badge badge-warning badge-sm">{{ __('shipping.mode.sandbox') }}</span>
                                        @else
                                            <span class="badge badge-ghost badge-sm">{{ __('shipping.mode.production') }}</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if ($connection->isActive())
                                            <span class="badge badge-success badge-sm">{{ __('shipping.status_label.active') }}</span>
                                        @else
                                            <span class="badge badge-ghost badge-sm">{{ __('shipping.status_label.inactive') }}</span>
                                        @endif
                                    </td>
                                    <td class="text-right">
                                        @if ($connection->isActive())
                                            <form method="POST" action="{{ route('admin.shipments.connections.disconnect') }}">
                                                @csrf
                                                <input type="hidden" name="connection" value="{{ $connection->sqid }}">
                                                <button type="submit" class="btn btn-ghost btn-xs text-error">{{ __('shipping.action.disconnect') }}</button>
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
