@extends('layouts.app')

@section('title', __('Vertragsverwaltung'))
@section('nav-title', __('Verträge'))

@section('content')
<x-index-page :subtitle="__('Verträge beliebiger Art mit Laufzeit, Kündigungsfrist, Indexierung und Vertragskalender — mit nächstmöglichem Kündigungstermin.')">
    <x-slot:actions>
        @can('create', \App\Models\Contract\Contract::class)
            <x-icon-btn icon="add" tone="primary" size="sm"
                        data-entry-modal-trigger
                        :href="route('contracts.create')"
                        show-label>{{ __('Neuer Vertrag') }}</x-icon-btn>
        @endcan
    </x-slot:actions>

    <div class="grid gap-4 sm:grid-cols-2">
        <x-kpi-tile :label="__('Laufende Verträge')" :value="$openCount" />
        <x-kpi-tile :label="__('Enden in ≤ 3 Monaten')" :value="$endingSoonCount" />
    </div>

    <x-filter-bar :action="route('contracts.index')" :reset="route('contracts.index')">
        <select name="status" class="select select-sm select-bordered w-44 shrink-0" aria-label="{{ __('Status') }}">
            <option value="">{{ __('Alle Status') }}</option>
            @foreach (\App\Enums\Contract\ContractStatus::cases() as $s)
                <option value="{{ $s->value }}" @selected(request('status') === $s->value)>{{ $s->label() }}</option>
            @endforeach
        </select>
        <select name="kind" class="select select-sm select-bordered w-52 shrink-0" aria-label="{{ __('Vertragsart') }}">
            <option value="">{{ __('Alle Vertragsarten') }}</option>
            @foreach (\App\Enums\Contract\ContractKind::cases() as $k)
                <option value="{{ $k->value }}" @selected(request('kind') === $k->value)>{{ $k->label() }}</option>
            @endforeach
        </select>
    </x-filter-bar>

    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    <x-card padding="p-0">
        <x-table bare>
            <x-slot:head>
                <tr>
                    <th>{{ __('Nummer') }}</th>
                    <th>{{ __('Titel') }}</th>
                    <th>{{ __('Vertragspartner') }}</th>
                    <th>{{ __('Art') }}</th>
                    <th>{{ __('Laufzeit') }}</th>
                    <th>{{ __('Nächste Kündigung zum') }}</th>
                    <th>{{ __('Status') }}</th>
                    <th></th>
                </tr>
            </x-slot:head>
            @forelse ($contracts as $contract)
                <tr>
                    <td><a href="{{ route('contracts.show', $contract) }}" class="link font-mono">{{ $contract->number }}</a></td>
                    <td>{{ $contract->title }}</td>
                    <td>{{ $contract->partnerLabel() ?: '—' }}</td>
                    <td>{{ $contract->kind->label() }}</td>
                    <td>{{ $contract->starts_on->fdate() }} – {{ optional($contract->ends_on)->fdate() ?? __('unbefristet') }}</td>
                    <td>{{ optional($nextTermination[$contract->id] ?? null)->fdate() ?? '—' }}</td>
                    <td><x-status-badge size="md" outline>{{ $contract->status->label() }}</x-status-badge></td>
                    <td class="text-right"><x-icon-btn icon="visibility" :href="route('contracts.show', $contract)" :label="__('Anzeigen')" /></td>
                </tr>
            @empty
                <x-table.empty icon='<span class="material-symbols-outlined" aria-hidden="true">contract</span>' :colspan="8" :title="__('Keine Verträge — über den Dialog anlegen.')" compact />
            @endforelse
        </x-table>
    </x-card>

    <x-pagination :paginator="$contracts" standing />
</x-index-page>
@endsection
