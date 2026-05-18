@extends('layouts.app')
@section('title', __('Plugins'))
@section('nav-title', __('Plugins'))

@section('content')
<x-page-shell>
    <x-slot:toolbar>
        <x-page-toolbar :title="__('Registrierte Plugins')" :subtitle="__('Plugins werden über config/plugins.php registriert. Konfiguration erfolgt aktuell über .env.')" />
    </x-slot:toolbar>

    <x-card padding="p-0">
        <x-table table-sort="client" bare>
            <x-slot:head>
                <tr>
                    <x-table.th sort type="string">{{ __('Name') }}</x-table.th>
                    <x-table.th sort type="string">{{ __('ID') }}</x-table.th>
                    <x-table.th sort type="number">{{ __('Version') }}</x-table.th>
                    <x-table.th sort type="string">{{ __('Status') }}</x-table.th>
                    <x-table.th sort type="string">{{ __('Capabilities') }}</x-table.th>
                    <x-table.th sort type="string">{{ __('Beschreibung') }}</x-table.th>
                </tr>
            </x-slot:head>
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
                <x-table.empty
                    icon='<span class="material-symbols-outlined" aria-hidden="true">extension</span>'
                    :colspan="6"
                    :title="__('Keine Plugins registriert')"
                    :message="__('Aktiviere z. B. Lexoffice mit LEXOFFICE_ENABLED=true und LEXOFFICE_API_KEY=… in der .env.')"
                    compact />
            @endforelse
        </x-table>
    </x-card>
</x-page-shell>
@endsection
