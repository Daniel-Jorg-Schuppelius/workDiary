@extends('layouts.app')
@section('title', __('allocation.dimensions.title'))
@section('nav-title', __('allocation.dimensions.title'))

@section('content')
<x-page-shell>
    <div class="space-y-4">
        @if (session('success'))
            <div class="alert alert-success text-sm">{{ session('success') }}</div>
        @endif
        <x-validation-errors first />

        <div class="rounded-box border border-base-300 bg-base-100 p-4 shadow-xs">
            <h1 class="mb-1 font-['Space_Grotesk'] text-lg font-semibold">{{ __('allocation.dimensions.title') }}</h1>
            <p class="text-sm text-base-content/60">{{ __('allocation.dimensions.intro') }}</p>
        </div>

        {{-- Neuer Dimensionstyp --}}
        <div class="rounded-box border border-base-300 bg-base-100 p-4 shadow-xs">
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
                <button type="submit" class="btn btn-sm btn-primary">{{ __('allocation.dimensions.create_type') }}</button>
            </form>
        </div>

        {{-- Typen + Werte --}}
        @forelse ($types as $type)
            <div class="rounded-box border border-base-300 bg-base-100 p-4 shadow-xs">
                <div class="mb-2 flex flex-wrap items-center gap-2">
                    <h2 class="font-['Space_Grotesk'] text-base font-semibold">{{ $type->name }}</h2>
                    <code class="rounded bg-base-200 px-2 py-0.5 text-xs">{{ $type->code }}</code>
                    <form method="POST" action="{{ route('admin.time-dimensions.types.toggle', $type) }}" class="ml-auto">
                        @csrf
                        <button type="submit" class="btn btn-ghost btn-xs">
                            @if ($type->enabled)
                                <span class="badge badge-success badge-sm">{{ __('allocation.dimensions.enabled') }}</span>
                            @else
                                <span class="badge badge-ghost badge-sm">{{ __('allocation.dimensions.disabled') }}</span>
                            @endif
                        </button>
                    </form>
                </div>

                @if ($type->values->isEmpty())
                    <p class="mb-3 text-sm text-base-content/60">{{ __('allocation.dimensions.no_values') }}</p>
                @else
                    <x-table class="mb-3">
                        <x-slot:head>
                            <tr>
                                <th>{{ __('allocation.dimensions.name') }}</th>
                                <th>{{ __('allocation.dimensions.external_id') }}</th>
                                <th>{{ __('allocation.dimensions.validity') }}</th>
                                <th></th>
                            </tr>
                        </x-slot:head>
                        @foreach ($type->values as $value)
                            <tr>
                                <td>{{ $value->name }}</td>
                                <td class="text-base-content/60">{{ $value->external_id ?? '—' }}</td>
                                <td class="text-base-content/60 whitespace-nowrap">
                                    @if ($value->valid_from || $value->valid_until)
                                        {{ $value->valid_from?->format('d.m.Y') ?? '…' }}–{{ $value->valid_until?->format('d.m.Y') ?? '…' }}
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="text-right">
                                    <form method="POST" action="{{ route('admin.time-dimensions.values.destroy', $value) }}" class="inline"
                                          data-confirm-dialog>
                                        @csrf @method('DELETE')
                                        <button type="submit" class="btn btn-ghost btn-xs text-error">{{ __('allocation.dimensions.delete_value') }}</button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </x-table>
                @endif

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
                    <label class="form-control">
                        <span class="label-text">{{ __('allocation.dimensions.valid_from') }}</span>
                        <input type="date" name="valid_from" class="input input-bordered input-sm">
                    </label>
                    <label class="form-control">
                        <span class="label-text">{{ __('allocation.dimensions.valid_until') }}</span>
                        <input type="date" name="valid_until" class="input input-bordered input-sm">
                    </label>
                    <button type="submit" class="btn btn-sm btn-primary">{{ __('allocation.dimensions.create_value') }}</button>
                </form>
            </div>
        @empty
            <div class="rounded-box border border-base-300 bg-base-100 p-4 text-sm text-base-content/60 shadow-xs">
                {{ __('allocation.dimensions.no_types') }}
            </div>
        @endforelse
    </div>
</x-page-shell>
@endsection
