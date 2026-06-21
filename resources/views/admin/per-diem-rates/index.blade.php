@extends('layouts.app')

@section('title', __('Verpflegungspauschalen'))
@section('nav-title', __('Verpflegungspauschalen'))
@section('wrapper-height-class', 'wd-page-fill')
@section('main-class', 'min-h-0 flex flex-col lg:overflow-clip')

@section('content')
<x-index-page overflow="clip" :subtitle="__('Pauschalensätze für Verpflegungs- und Übernachtungskosten verwalten.')">
    <x-slot:actions>
        <form method="GET" action="{{ route('admin.per-diem-rates.index') }}" class="inline-flex items-center gap-2">
            <input type="text" name="country" maxlength="2" placeholder="DE"
                   value="{{ $country ?? '' }}"
                   class="input input-bordered input-sm w-20 uppercase">
            <button type="submit" class="btn btn-ghost btn-sm">{{ __('Filtern') }}</button>
        </form>
        <x-icon-btn icon="add" tone="primary" size="sm"
                    data-entry-modal-trigger
                    :href="route('admin.per-diem-rates.create')"
                    show-label>{{ __('Pauschalensatz anlegen') }}</x-icon-btn>
    </x-slot:actions>

    <div role="alert" class="alert alert-info alert-soft">
        <x-icon name="info" />
        <div>
            <h3 class="font-semibold">{{ __('Pauschalensätze') }}</h3>
            <div class="text-sm">
                {{ __('Diese Tabelle enthält die je Land und Zeitraum gültigen Tagespauschalen für Verpflegungsmehraufwand (BMF-Sätze). Die Werte werden bei der Berechnung neuer Reisen herangezogen.') }}
            </div>
        </div>
    </div>

    <x-table scroll="flex" :pinRows="true" table-sort="server"
             :route="route('admin.per-diem-rates.index')"
             :current-sort="$sort ?? null"
             :current-dir="$dir ?? 'desc'">
        <x-slot:head>
            <tr>
                <x-table.th sort="country">{{ __('Land') }}</x-table.th>
                <x-table.th sort="region">{{ __('Region') }}</x-table.th>
                <x-table.th sort="valid_from">{{ __('Gültig ab') }}</x-table.th>
                <x-table.th sort="valid_to">{{ __('Gültig bis') }}</x-table.th>
                <x-table.th sort="full" align="right">{{ __('Vollständig') }}</x-table.th>
                <x-table.th sort="partial" align="right">{{ __('Teil') }}</x-table.th>
                <th align="right">{{ __('Übernachtung') }}</th>
                <th>{{ __('Währung') }}</th>
                <th>{{ __('Quelle') }}</th>
                <th></th>
            </tr>
        </x-slot:head>
        @forelse ($rates as $rate)
            <tr>
                <td class="font-mono uppercase">{{ $rate->country }}</td>
                <td>{{ $rate->region_label ?? '—' }}</td>
                <td>{{ \Illuminate\Support\Carbon::parse($rate->valid_from)->fdate() }}</td>
                <td>{{ $rate->valid_to ? \Illuminate\Support\Carbon::parse($rate->valid_to)->fdate() : '—' }}</td>
                <td class="text-right tabular-nums">{{ number_format((float) $rate->full_day_amount, 2, ',', '.') }}</td>
                <td class="text-right tabular-nums">{{ number_format((float) $rate->partial_day_amount, 2, ',', '.') }}</td>
                <td class="text-right tabular-nums">{{ $rate->overnight_amount !== null ? number_format((float) $rate->overnight_amount, 2, ',', '.') : '—' }}</td>
                <td>{{ $rate->currency }}</td>
                <td class="text-base-content/60 text-sm">{{ $rate->source ?? '—' }}</td>
                <td class="text-right">
                    <div class="flex justify-end gap-1">
                        <x-icon-btn icon="edit"
                                    data-entry-modal-trigger
                                    :href="route('admin.per-diem-rates.edit', $rate)"
                                    :label="__('Bearbeiten')" />
                        <x-action-form :action="route('admin.per-diem-rates.destroy', $rate)" method="DELETE"
                              :confirm="__('Pauschalensatz wirklich löschen?')"
                              :confirm-label="__('Löschen')">
                            <x-icon-btn icon="delete" tone="error" type="submit" :label="__('Löschen')" />
                        </x-action-form>
                    </div>
                </td>
            </tr>
        @empty
            <x-table.empty icon='<span class="material-symbols-outlined" aria-hidden="true">restaurant_menu</span>' :colspan="10" :title="__('Keine Pauschalensätze vorhanden')" compact />
        @endforelse
    </x-table>

    <x-pagination :paginator="$rates" />
</x-index-page>
@endsection
