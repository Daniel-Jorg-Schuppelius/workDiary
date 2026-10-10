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
        <x-validation-errors first />

        <x-page-toolbar :subtitle="__('shipping.intro')" />

        {{-- Anbindung anlegen bzw. im Bearbeiten-Modus ändern (je Carrier eine Anbindung) --}}
        <x-card as="form" method="POST" action="{{ route('admin.shipments.connections.store') }}">
            @csrf
            @if ($editing)
                <input type="hidden" name="connection" value="{{ $editing->sqid }}">
                <input type="hidden" name="carrier" value="{{ $editing->carrier }}">
                <h2 class="mb-3 font-['Space_Grotesk'] text-base font-semibold">{{ __('shipping.form_heading_edit', ['carrier' => strtoupper($editing->carrier)]) }}</h2>
            @else
                <h2 class="mb-1 font-['Space_Grotesk'] text-base font-semibold">{{ __('shipping.form_heading') }}</h2>
                <p class="mb-3 text-xs text-muted">{{ __('shipping.form_hint') }}</p>
            @endif

            <div class="grid gap-3 sm:grid-cols-2">
                @unless ($editing)
                    <label class="form-control">
                        <span class="label-text">{{ __('shipping.field.carrier') }}</span>
                        <select name="carrier" class="select select-bordered select-sm" required>
                            @foreach ($carriers as $carrier)
                                <option value="{{ $carrier }}" @selected(old('carrier') === $carrier)>{{ strtoupper($carrier) }}</option>
                            @endforeach
                        </select>
                    </label>
                @endunless
                <label class="form-control">
                    <span class="label-text">{{ __('shipping.field.name') }}</span>
                    <input type="text" name="name" value="{{ old('name', $editing?->name) }}" class="input input-bordered input-sm" maxlength="120" required>
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
                    <span class="label-text">{{ __('shipping.field.returns_receiver_id') }}</span>
                    <input type="text" name="returns_receiver_id" value="{{ old('returns_receiver_id') }}" class="input input-bordered input-sm" maxlength="60" title="{{ __('shipping.field.returns_receiver_id_hint') }}">
                </label>
                <label class="form-control">
                    <span class="label-text">{{ __('shipping.field.billing_number') }}</span>
                    <input type="text" name="billing_number" value="{{ old('billing_number', $editing?->billing_number) }}" class="input input-bordered input-sm" maxlength="60">
                </label>
            </div>

            <div class="mt-3 flex flex-wrap items-center gap-4">
                <label class="label cursor-pointer gap-2">
                    <input type="checkbox" name="sandbox" value="1" class="checkbox checkbox-sm" @checked(old('sandbox', $editing?->sandbox ?? false))>
                    <span class="label-text">{{ __('shipping.field.sandbox') }}</span>
                </label>
                <label class="label cursor-pointer gap-2">
                    <input type="checkbox" name="active" value="1" class="checkbox checkbox-sm" @checked(old('active', $editing?->active ?? true))>
                    <span class="label-text">{{ __('shipping.field.active') }}</span>
                </label>
                <span class="ml-auto flex gap-2">
                    @if ($editing)
                        <x-button tone="ghost" :href="route('admin.shipments.connections.index')">{{ __('shipping.action.cancel_edit') }}</x-button>
                    @endif
                    <x-button type="submit">{{ __('shipping.action.save') }}</x-button>
                </span>
            </div>
            <p class="mt-2 text-xs text-muted">{{ __('shipping.secret_hint') }}</p>
        </x-card>

        {{-- Bestehende Anbindungen --}}
        <x-card>
            <h2 class="mb-2 font-['Space_Grotesk'] text-base font-semibold">{{ __('shipping.connections_heading') }}</h2>
            <x-table :bare="true" :empty-title="__('shipping.no_connections')">
                <x-slot:head>
                    <tr>
                        <th>{{ __('shipping.field.carrier') }}</th>
                        <th>{{ __('shipping.field.name') }}</th>
                        <th>{{ __('shipping.col.mode') }}</th>
                        <th>{{ __('shipping.col.status') }}</th>
                        <th class="text-right"></th>
                    </tr>
                </x-slot:head>
                            @foreach ($connections as $connection)
                                <tr class="hover">
                                    <td class="font-medium">{{ strtoupper($connection->carrier) }}</td>
                                    <td>{{ $connection->name }}</td>
                                    <td>
                                        @if ($connection->sandbox)
                                            <x-status-badge tone="warning">{{ __('shipping.mode.sandbox') }}</x-status-badge>
                                        @else
                                            <x-status-badge>{{ __('shipping.mode.production') }}</x-status-badge>
                                        @endif
                                    </td>
                                    <td>
                                        @if ($connection->isActive())
                                            <x-status-badge tone="success">{{ __('shipping.status_label.active') }}</x-status-badge>
                                        @else
                                            <x-status-badge>{{ __('shipping.status_label.inactive') }}</x-status-badge>
                                        @endif
                                    </td>
                                    <td class="text-right whitespace-nowrap">
                                        <x-button tone="ghost" size="xs" :href="route('admin.shipments.connections.index', ['edit' => $connection->sqid])">{{ __('shipping.action.edit') }}</x-button>
                                        @if ($connection->isActive())
                                            <form method="POST" action="{{ route('admin.shipments.connections.disconnect') }}" class="inline">
                                                @csrf
                                                <input type="hidden" name="connection" value="{{ $connection->sqid }}">
                                                <x-button type="submit" tone="ghost" size="xs" class="text-error">{{ __('shipping.action.disconnect') }}</x-button>
                                            </form>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
            </x-table>
        </x-card>
    </div>
</x-page-shell>
@endsection
