@extends('layouts.app')
@section('title', __('Plugins'))
@section('nav-title', __('Plugins'))

@section('content')
<x-page-shell>
    <x-slot:toolbar>
        <x-page-toolbar :title="__('Registrierte Plugins')" :subtitle="__('Plugins werden über config/plugins.php registriert. Konfiguration erfolgt aktuell über .env.')" />
    </x-slot:toolbar>

    <x-card padding="p-0">
        <div class="overflow-x-auto">
            <table class="table table-sm">
                <thead>
                    <tr>
                        <th>{{ __('Name') }}</th>
                        <th>{{ __('ID') }}</th>
                        <th>{{ __('Version') }}</th>
                        <th>{{ __('Status') }}</th>
                        <th>{{ __('Capabilities') }}</th>
                        <th>{{ __('Beschreibung') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($plugins as $plugin)
                        <tr>
                            <td class="font-medium">{{ $plugin->name() }}</td>
                            <td><code class="text-xs">{{ $plugin->id() }}</code></td>
                            <td class="tabular-nums">{{ $plugin->version() }}</td>
                            <td>
                                @if ($plugin->isEnabled())
                                    <span class="badge badge-success badge-sm">{{ __('aktiv') }}</span>
                                @else
                                    <span class="badge badge-ghost badge-sm">{{ __('inaktiv') }}</span>
                                @endif
                            </td>
                            <td>
                                <div class="flex flex-wrap gap-1">
                                    @foreach ($plugin->capabilities() as $cap)
                                        <span class="badge badge-outline badge-sm">{{ $cap }}</span>
                                    @endforeach
                                </div>
                            </td>
                            <td class="text-sm text-base-content/70">{{ $plugin->description() }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="p-0">
                                <x-empty-state
                                    :compact="true"
                                    :title="__('Keine Plugins registriert')"
                                    :message="__('Aktiviere z. B. Lexoffice mit LEXOFFICE_ENABLED=true und LEXOFFICE_API_KEY=… in der .env.')" />
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-card>
</x-page-shell>
@endsection
