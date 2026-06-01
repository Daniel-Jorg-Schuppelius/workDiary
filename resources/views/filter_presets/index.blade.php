@extends('layouts.app')
@section('wrapper-height-class', 'min-h-[calc(100dvh_-_var(--app-header-h))] lg:h-[calc(100dvh_-_var(--app-header-h))] lg:overflow-clip')
@section('main-class', 'min-h-0 flex flex-col lg:overflow-clip')

@section('content')
    <x-index-page overflow="clip" :subtitle="__('Gespeicherte Filter pro Ansicht.')">

        @if (session('status'))
            <div class="alert alert-success">{{ session('status') }}</div>
        @endif

        @if ($presets->isEmpty())
            <x-empty-state framed
                icon='<span class="material-symbols-outlined" aria-hidden="true">filter_alt</span>'
                :title="__('Noch keine Filter-Presets gespeichert.')" />
        @else
            <x-card padding="p-0" class="min-h-0 flex-1 flex flex-col overflow-hidden">
                <x-table zebra bare scroll="flex" :pinRows="true">
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
                                <td><x-status-badge size="md" outline>{{ $preset->scope }}</x-status-badge></td>
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
            </x-card>
        @endif
    </x-index-page>
@endsection
