@extends('layouts.app')

@section('title', __('Legacy-Migration'))
@section('nav-title', __('Legacy-Migration'))

@section('content')
<div class="flex h-[calc(100dvh-11rem)] flex-col gap-6 overflow-auto">
    <div class="flex justify-end">
        @if ($writeEnabled)
            <span class="badge badge-warning">{{ __('Legacy-Schreibzugriff aktiv') }}</span>
        @else
            <span class="badge badge-success">{{ __('Legacy read-only') }}</span>
        @endif
    </div>

    @if (! $stats['configured'])
        <div class="alert alert-warning">
            <span>{{ __('Legacy-Datenbank nicht erreichbar oder nicht konfiguriert.') }}</span>
        </div>
    @else
        <x-table>
                <thead>
                    <tr>
                        <th>{{ __('Datentyp') }}</th>
                        <th class="text-right">{{ __('Legacy gesamt') }}</th>
                        <th class="text-right">{{ __('Bereits importiert') }}</th>
                        <th class="text-right">{{ __('Verbleibend') }}</th>
                        <th class="text-right">{{ __('Aktion') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach (['users' => __('Benutzer'), 'diary' => __('Tagebuch'), 'shifts' => __('Bereitschaften'), 'assignments' => __('Notdienste')] as $key => $label)
                        @php $row = $stats[$key]; $remaining = max(0, $row['legacy'] - $row['imported']); @endphp
                        <tr>
                            <td class="font-medium">{{ $label }}</td>
                            <td class="text-right">{{ number_format($row['legacy'], 0, ',', '.') }}</td>
                            <td class="text-right">{{ number_format($row['imported'], 0, ',', '.') }}</td>
                            <td class="text-right">
                                @if ($remaining === 0)
                                    <span class="badge badge-success badge-sm">{{ __('Vollständig') }}</span>
                                @else
                                    <span class="text-warning">{{ number_format($remaining, 0, ',', '.') }}</span>
                                @endif
                            </td>
                            <td class="text-right">
                                <form method="POST" action="{{ route('admin.legacy-migration.run') }}">
                                    @csrf
                                    <input type="hidden" name="type" value="{{ $key }}">
                                    <button type="submit" class="btn btn-sm btn-primary">{{ __('Importieren') }}</button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
        </x-table>

        <form method="POST" action="{{ route('admin.legacy-migration.run') }}">
            @csrf
            <input type="hidden" name="type" value="all">
            <button type="submit" class="btn btn-primary">{{ __('Alles importieren') }}</button>
        </form>
    @endif
</div>
@endsection
