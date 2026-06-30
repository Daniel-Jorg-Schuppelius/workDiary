@extends('layouts.app')
@section('title', __('Kunden-Abgleich'))
@section('nav-title', __('Kunden-Abgleich'))

@php
    use App\Services\CustomerDuplicateFinder;

    $reasonLabels = [
        'vat_id' => __('USt-IdNr.'),
        'lexoffice_contact_number' => __('Lexoffice-Nr.'),
        'email' => __('E-Mail'),
        'company_zip' => __('Firma + PLZ'),
        'name' => __('Name/Firma ähnlich'),
    ];
    $confidenceLabels = [
        CustomerDuplicateFinder::CONF_EXACT  => __('Eindeutig'),
        CustomerDuplicateFinder::CONF_LIKELY => __('Wahrscheinlich'),
        CustomerDuplicateFinder::CONF_FUZZY  => __('Möglich'),
    ];
    // Felder, die im Vergleich gegenübergestellt werden.
    $compareFields = [
        'name' => __('Name'),
        'company' => __('Firma'),
        'number' => __('Kundennr.'),
        'lexoffice_contact_number' => __('Lexoffice-Nr.'),
        'vat_id' => __('USt-IdNr.'),
        'email' => __('E-Mail'),
        'address_zip' => __('PLZ'),
        'address_city' => __('Ort'),
    ];
@endphp

@section('content')
<x-index-page :subtitle="__('Doppelte Kunden (z. B. nach dem Toggl-Import) werden hier gegenübergestellt. Pro Paar entscheidest du, welcher Datensatz bestehen bleibt — alle Projekte, Zeiten, Rechnungen und Referenzen werden auf ihn umgehängt, der andere wird gelöscht.')">
    <x-slot:actions>
        <form method="GET" action="{{ route('customers.duplicates.index') }}" class="flex items-center gap-2">
            <select name="confidence" class="select select-sm select-bordered" onchange="this.form.submit()">
                <option value="all" @selected($confidence === 'all')>{{ __('Alle Stufen') }}</option>
                <option value="{{ CustomerDuplicateFinder::CONF_EXACT }}"  @selected($confidence === CustomerDuplicateFinder::CONF_EXACT)>{{ $confidenceLabels[CustomerDuplicateFinder::CONF_EXACT] }}</option>
                <option value="{{ CustomerDuplicateFinder::CONF_LIKELY }}" @selected($confidence === CustomerDuplicateFinder::CONF_LIKELY)>{{ $confidenceLabels[CustomerDuplicateFinder::CONF_LIKELY] }}</option>
                <option value="{{ CustomerDuplicateFinder::CONF_FUZZY }}"  @selected($confidence === CustomerDuplicateFinder::CONF_FUZZY)>{{ $confidenceLabels[CustomerDuplicateFinder::CONF_FUZZY] }}</option>
            </select>
        </form>
    </x-slot:actions>

    @if ($candidates->isEmpty())
        <p class="rounded-box border border-base-300 bg-base-100 p-6 text-center text-sm text-base-content/60">
            {{ __('Keine Dubletten-Kandidaten im gewählten Filter. 🎉') }}
        </p>
    @else
        <div class="space-y-4">
            @foreach ($candidates as $pair)
                @php
                    $target = $pair['target'];
                    $source = $pair['source'];
                    $conf = $pair['confidence'];
                @endphp
                <div class="rounded-box border border-base-300 bg-base-100 p-4 shadow-xs">
                    <div class="mb-3 flex flex-wrap items-center gap-2">
                        <span class="badge badge-sm
                            @if ($conf === CustomerDuplicateFinder::CONF_EXACT) badge-error
                            @elseif ($conf === CustomerDuplicateFinder::CONF_LIKELY) badge-warning
                            @else badge-ghost @endif">{{ $confidenceLabels[$conf] ?? $conf }}</span>
                        @foreach ($pair['reasons'] as $reason)
                            <span class="badge badge-sm badge-outline">{{ $reasonLabels[$reason] ?? $reason }}</span>
                        @endforeach
                    </div>

                    <div class="overflow-x-auto">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th class="w-40">{{ __('Feld') }}</th>
                                    <th>
                                        <span class="badge badge-sm badge-success">{{ __('Bleibt') }}</span>
                                        <a href="{{ route('customers.show', $target) }}" class="link ml-1">{{ $target->name }}</a>
                                    </th>
                                    <th>
                                        <span class="badge badge-sm badge-ghost">{{ __('Wird gelöscht') }}</span>
                                        <a href="{{ route('customers.show', $source) }}" class="link ml-1">{{ $source->name }}</a>
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($compareFields as $field => $label)
                                    @php
                                        $tv = (string) ($target->getAttribute($field) ?? '');
                                        $sv = (string) ($source->getAttribute($field) ?? '');
                                    @endphp
                                    @if ($tv !== '' || $sv !== '')
                                        <tr>
                                            <td class="text-base-content/60">{{ $label }}</td>
                                            <td class="{{ $tv === '' ? 'text-base-content/30' : '' }}">{{ $tv !== '' ? $tv : '—' }}</td>
                                            <td class="{{ $tv !== $sv ? 'text-warning' : 'text-base-content/50' }}">{{ $sv !== '' ? $sv : '—' }}</td>
                                        </tr>
                                    @endif
                                @endforeach
                                <tr>
                                    <td class="text-base-content/60">{{ __('Projekte') }}</td>
                                    <td>{{ (int) ($target->projects_count ?? 0) }}</td>
                                    <td>{{ (int) ($source->projects_count ?? 0) }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="mt-3 flex flex-wrap justify-end gap-2">
                        <form method="POST" action="{{ route('customers.duplicates.merge') }}"
                              onsubmit="return confirm(@js(__('„:source" endgültig in „:target" zusammenführen? Der Quell-Kunde wird gelöscht.', ['source' => $source->name, 'target' => $target->name])));">
                            @csrf
                            <input type="hidden" name="source" value="{{ $source->sqid }}">
                            <input type="hidden" name="target" value="{{ $target->sqid }}">
                            <button class="btn btn-sm btn-primary">{{ __('Zusammenführen →') }}</button>
                        </form>
                        <form method="POST" action="{{ route('customers.duplicates.merge') }}"
                              onsubmit="return confirm(@js(__('Richtung tauschen: „:source" in „:target" zusammenführen?', ['source' => $target->name, 'target' => $source->name])));">
                            @csrf
                            <input type="hidden" name="source" value="{{ $target->sqid }}">
                            <input type="hidden" name="target" value="{{ $source->sqid }}">
                            <button class="btn btn-sm btn-outline">{{ __('Umgekehrt') }}</button>
                        </form>
                        <form method="POST" action="{{ route('customers.duplicates.dismiss') }}">
                            @csrf
                            <input type="hidden" name="source" value="{{ $source->sqid }}">
                            <input type="hidden" name="target" value="{{ $target->sqid }}">
                            <button class="btn btn-sm btn-ghost">{{ __('Kein Duplikat') }}</button>
                        </form>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</x-index-page>
@endsection
