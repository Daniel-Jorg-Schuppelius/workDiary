@extends('layouts.app')

@section('content')
    <x-page-shell gap="gap-6">
        <x-page-toolbar
            :title="__('Filter-Presets')"
            :subtitle="__('Gespeicherte Filter pro Ansicht.')" />

        @if (session('status'))
            <div class="alert alert-success">{{ session('status') }}</div>
        @endif

        <x-card padding="p-0">
            @if ($presets->isEmpty())
                <div class="p-6 text-center text-sm text-base-content/60">
                    {{ __('Noch keine Filter-Presets gespeichert.') }}
                </div>
            @else
                <x-table zebra>
                    <thead>
                        <tr>
                            <th>{{ __('Bereich') }}</th>
                            <th>{{ __('Name') }}</th>
                            <th>{{ __('Standard') }}</th>
                            <th class="text-right">{{ __('Aktionen') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($presets as $preset)
                            <tr>
                                <td><span class="badge badge-outline">{{ $preset->scope }}</span></td>
                                <td>{{ $preset->name }}</td>
                                <td>
                                    @if ($preset->is_default)
                                        <x-icon name="check_circle" class="text-success" />
                                    @endif
                                </td>
                                <td class="text-right">
                                    <form method="POST" action="{{ route('filter-presets.destroy', $preset) }}" class="inline">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-ghost btn-xs" data-confirm-dialog data-confirm-message="{{ __('Wirklich löschen?') }}">
                                            <x-icon name="delete" /> {{ __('Löschen') }}
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </x-table>
            @endif
        </x-card>
    </x-page-shell>
@endsection
