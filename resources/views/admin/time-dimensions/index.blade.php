{{--
  Created on   : Wed Aug 12 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@extends('layouts.app')
@section('title', __('allocation.dimensions.title'))
@section('nav-title', __('allocation.dimensions.title'))
@include('partials.page-fill')

@section('content')
<x-index-page overflow="clip" :subtitle="__('allocation.dimensions.intro')">
    <x-validation-errors first />

    {{-- Neuer Dimensionstyp --}}
    <x-card class="shrink-0">
        <h2 class="mb-2 font-['Space_Grotesk'] text-base font-semibold">{{ __('allocation.dimensions.new_type') }}</h2>
        <form method="POST" action="{{ route('admin.time-dimensions.types.store') }}" class="flex flex-wrap items-end gap-2">
            @csrf
            <label class="form-control">
                <span class="label-text">{{ __('allocation.dimensions.code') }}</span>
                <input type="text" name="code" value="{{ old('code') }}" maxlength="40" pattern="[a-z0-9][a-z0-9._\-]*"
                       placeholder="erp-auftrag" class="input input-bordered input-sm font-mono" required>
            </label>
            <label class="form-control grow">
                <span class="label-text">{{ __('allocation.dimensions.name') }}</span>
                <input type="text" name="name" value="{{ old('name') }}" maxlength="120" class="input input-bordered input-sm" required>
            </label>
            <x-button type="submit">{{ __('allocation.dimensions.create_type') }}</x-button>
        </form>
    </x-card>

    {{-- Je Typ eine Kopfzeile, seine Werte und die Zeile zum Anlegen eines Werts. --}}
    <x-table scroll="flex" :pinRows="true" :zebra="false" :empty-title="__('allocation.dimensions.no_types')">
        <x-slot:head>
            <tr>
                <th>{{ __('allocation.dimensions.name') }}</th>
                <th>{{ __('allocation.dimensions.external_id') }}</th>
                <th>{{ __('allocation.dimensions.validity') }}</th>
                <x-table.th align="right"><span class="sr-only">{{ __('Aktionen') }}</span></x-table.th>
            </tr>
        </x-slot:head>
        @foreach ($types as $type)
            <tr class="bg-base-200/60">
                <td colspan="4">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="font-['Space_Grotesk'] font-semibold">{{ $type->name }}</span>
                        <code class="rounded bg-base-100 px-2 py-0.5 text-xs">{{ $type->code }}</code>
                        <form method="POST" action="{{ route('admin.time-dimensions.types.toggle', $type) }}" class="ml-auto">
                            @csrf
                            <x-button type="submit" tone="ghost" size="xs">
                                @if ($type->enabled)
                                    <x-status-badge tone="success">{{ __('allocation.dimensions.enabled') }}</x-status-badge>
                                @else
                                    <x-status-badge>{{ __('allocation.dimensions.disabled') }}</x-status-badge>
                                @endif
                            </x-button>
                        </form>
                    </div>
                </td>
            </tr>
            @forelse ($type->values as $value)
                <tr class="hover">
                    <td>{{ $value->name }}</td>
                    <td class="text-muted">{{ $value->external_id ?? '—' }}</td>
                    <td class="text-muted whitespace-nowrap">
                        @if ($value->valid_from || $value->valid_until)
                            {{ $value->valid_from?->fdate() ?? '…' }}–{{ $value->valid_until?->fdate() ?? '…' }}
                        @else
                            —
                        @endif
                    </td>
                    <td class="text-right">
                        <form method="POST" action="{{ route('admin.time-dimensions.values.destroy', $value) }}" class="inline"
                              data-confirm-dialog>
                            @csrf @method('DELETE')
                            <x-button type="submit" tone="ghost" size="xs" class="text-error">{{ __('allocation.dimensions.delete_value') }}</x-button>
                        </form>
                    </td>
                </tr>
            @empty
                <x-table.empty :colspan="4" compact :title="__('allocation.dimensions.no_values')" />
            @endforelse
            <tr>
                <td colspan="4">
                    <form method="POST" action="{{ route('admin.time-dimensions.values.store', $type) }}" class="flex flex-wrap items-end gap-2">
                        @csrf
                        <label class="form-control grow">
                            <span class="label-text">{{ __('allocation.dimensions.name') }}</span>
                            <input type="text" name="name" maxlength="160" class="input input-bordered input-sm" required>
                        </label>
                        <label class="form-control">
                            <span class="label-text">{{ __('allocation.dimensions.external_id') }}</span>
                            <input type="text" name="external_id" maxlength="120" class="input input-bordered input-sm">
                        </label>
                        <x-date-range layout="split" form-control grid-class="contents"
                                      from-name="valid_from" to-name="valid_until" type="date"
                                      :from-label="__('allocation.dimensions.valid_from')"
                                      :to-label="__('allocation.dimensions.valid_until')" />
                        <x-button type="submit">{{ __('allocation.dimensions.create_value') }}</x-button>
                    </form>
                </td>
            </tr>
        @endforeach
    </x-table>

    <x-pagination :paginator="$types" standing />
</x-index-page>
@endsection
