@extends('layouts.app')
@section('title', __('Schichttypen'))
@section('nav-title', __('Schichttypen'))

@section('content')
    <div class="space-y-6">
        <div class="flex justify-end">
            <a href="{{ route('shift-types.create') }}" data-entry-modal-trigger class="btn btn-primary btn-sm">{{ __('Neuer Schichttyp') }}</a>
        </div>

        @if (session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif
        @if (session('error'))
            <div class="alert alert-error">{{ session('error') }}</div>
        @endif

        <div class="overflow-x-auto rounded-box border border-base-300 bg-base-100 shadow-xs">
            <table class="table table-zebra table-pin-rows">
                <thead class="bg-base-200">
                    <tr>
                        <th>{{ __('Name') }}</th>
                        <th>{{ __('Kürzel') }}</th>
                        <th>{{ __('Standardzeit') }}</th>
                        <th>{{ __('Status') }}</th>
                        <th class="text-right">{{ __('Verwendet') }}</th>
                        <th class="w-32 text-right">{{ __('Aktion') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($types as $type)
                        <tr class="hover">
                            <td class="flex items-center gap-2">
                                <span class="inline-block w-3 h-3 rounded" style="background:{{ $type->color }}"></span>
                                {{ $type->name }}
                            </td>
                            <td><span class="font-mono">{{ $type->abbreviation }}</span></td>
                            <td class="whitespace-nowrap">
                                @if ($type->default_start_time && $type->default_end_time)
                                    {{ $type->default_start_time }}–{{ $type->default_end_time }}
                                @else — @endif
                            </td>
                            <td>
                                @if ($type->is_active)
                                    <span class="badge badge-success badge-sm">{{ __('Aktiv') }}</span>
                                @else
                                    <span class="badge badge-ghost badge-sm">{{ __('Inaktiv') }}</span>
                                @endif
                            </td>
                            <td class="text-right">{{ $type->scheduled_shifts_count }}</td>
                            <td class="text-right whitespace-nowrap">
                                <a href="{{ route('shift-types.edit', $type) }}" data-entry-modal-trigger class="btn btn-ghost btn-sm">{{ __('Bearbeiten') }}</a>
                                <form action="{{ route('shift-types.destroy', $type) }}" method="POST" class="inline"
                                      onsubmit="return confirm('{{ __('Wirklich löschen?') }}')">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="btn btn-ghost btn-sm text-error">{{ __('Löschen') }}</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="py-6 text-center text-base-content/60">{{ __('Keine Einträge.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
