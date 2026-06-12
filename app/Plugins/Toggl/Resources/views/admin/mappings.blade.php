@extends('layouts.app')
@section('title', __('Toggl-Zuordnungen'))
@section('nav-title', __('Toggl-Zuordnungen'))

@php
    // id → Label für die Projekt-Anzeige (Projekt (Kunde)).
    $customerLabel = collect($customers)->keyBy('id')->map(fn($c) => $c['label']);
@endphp

@section('content')
<x-page-shell>
    <div class="rounded-box border border-base-300 bg-base-100 p-4 shadow-xs">
        <div class="mb-1 flex flex-wrap items-center justify-between gap-2">
            <h1 class="font-['Space_Grotesk'] text-lg font-semibold">{{ __('Gemerkte Zuordnungen') }}</h1>
            <a href="{{ route('admin.toggl.index') }}" class="btn btn-ghost btn-sm">{{ __('Zurück zum Import') }}</a>
        </div>
        <p class="mb-4 text-sm text-base-content/60">
            {{ __('Diese Zuordnungen entscheiden, welchem Kunden bzw. Projekt ein Toggl-Client/-Projekt bei künftigen Importen automatisch zugewiesen wird. Hier kannst du sie umbiegen oder entfernen.') }}
        </p>

        @if (session('status'))
            <div class="alert alert-success mb-3 text-sm">{{ session('status') }}</div>
        @endif
        @if ($errors->any())
            <div class="alert alert-error mb-3 text-sm">{{ $errors->first() }}</div>
        @endif

        @if ($mappings->isEmpty())
            <p class="rounded-box border border-base-300 p-6 text-center text-sm text-base-content/60">
                {{ __('Noch keine Zuordnungen gemerkt.') }}
            </p>
        @else
            <div class="overflow-x-auto">
                <table class="table table-sm">
                    <thead>
                        <tr>
                            <th>{{ __('Typ') }}</th>
                            <th>{{ __('Toggl') }}</th>
                            <th>{{ __('Zugeordnet zu') }}</th>
                            <th class="text-right">{{ __('Aktion') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($mappings as $mapping)
                            @php
                                $isClient = $mapping->external_type === \App\Plugins\Toggl\TogglImportService::EXT_TYPE_CLIENT;
                                $target = $mapping->referenceable;
                                $currentSqid = $target?->sqid;
                            @endphp
                            <tr>
                                <td>
                                    <x-status-badge :tone="$isClient ? 'info' : 'neutral'" size="sm">
                                        {{ $isClient ? __('Kunde') : __('Projekt') }}
                                    </x-status-badge>
                                </td>
                                <td class="font-mono text-xs">{{ $mapping->external_id }}</td>
                                <td>
                                    @if ($target === null)
                                        <span class="text-error text-xs">{{ __('verwaist (Ziel gelöscht)') }}</span>
                                    @elseif ($isClient)
                                        {{ $target->company ?: $target->name }}
                                    @else
                                        {{ $target->name }}
                                        <span class="text-base-content/50">({{ $customerLabel[$target->customer_id] ?? '—' }})</span>
                                    @endif
                                </td>
                                <td>
                                    <div class="flex items-center justify-end gap-2">
                                        <form method="POST" action="{{ route('admin.toggl.mappings.update', $mapping->id) }}"
                                              class="flex items-center gap-2">
                                            @csrf
                                            <select name="target_id" required class="select select-sm select-bordered">
                                                <option value="">{{ __('— wählen —') }}</option>
                                                @if ($isClient)
                                                    @foreach ($customers as $c)
                                                        <option value="{{ $c['sqid'] }}" @selected($c['sqid'] === $currentSqid)>{{ $c['label'] }}</option>
                                                    @endforeach
                                                @else
                                                    @foreach ($projects as $p)
                                                        <option value="{{ $p['sqid'] }}" @selected($p['sqid'] === $currentSqid)>
                                                            {{ $p['name'] }} ({{ $customerLabel[$p['customer_id']] ?? '—' }})
                                                        </option>
                                                    @endforeach
                                                @endif
                                            </select>
                                            <button type="submit" class="btn btn-sm">{{ __('Umbiegen') }}</button>
                                        </form>
                                        <form method="POST" action="{{ route('admin.toggl.mappings.delete', $mapping->id) }}"
                                              data-confirm-dialog
                                              data-confirm-message="{{ __('Diese Zuordnung entfernen? Künftige Importe matchen dann nicht mehr automatisch.') }}">
                                            @csrf
                                            <button type="submit" class="btn btn-ghost btn-sm text-error">{{ __('Entfernen') }}</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</x-page-shell>
@endsection
