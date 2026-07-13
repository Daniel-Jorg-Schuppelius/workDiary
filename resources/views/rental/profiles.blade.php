@extends('layouts.app')

@section('title', __('Gerätepool'))
@section('nav-title', __('Gerätepool'))

@section('content')
<x-index-page :subtitle="__('Leihfähige Geräte und Maschinen mit Gerätegruppe, Pufferzeiten, Prüfpflicht und Standard-Preisliste.')">
    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif
    @if ($errors->any())
        <div class="alert alert-error text-sm">
            <ul class="list-disc pl-5">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
        </div>
    @endif

    <x-filter-bar :action="route('rental.profiles.index')" :reset="route('rental.profiles.index')">
        <select name="group" class="select select-sm select-bordered w-44 shrink-0" aria-label="{{ __('Gerätegruppe') }}">
            <option value="">{{ __('Alle Gruppen') }}</option>
            @foreach ($groups as $group)
                <option value="{{ $group }}" @selected(request('group') === $group)>{{ $group }}</option>
            @endforeach
        </select>
    </x-filter-bar>

    <x-card padding="p-0">
        <x-table bare>
            <x-slot:head>
                <tr>
                    <th>{{ __('Asset') }}</th>
                    <th>{{ __('Gruppe') }}</th>
                    <th>{{ __('Leihfähig') }}</th>
                    <th>{{ __('Puffer (vor/nach)') }}</th>
                    <th>{{ __('Prüfpflicht') }}</th>
                    <th>{{ __('Sperren') }}</th>
                    <th>{{ __('Preisliste') }}</th>
                </tr>
            </x-slot:head>
            @forelse ($profiles as $profile)
                <tr>
                    <td>{{ $profile->asset->name ?? '—' }}</td>
                    <td>{{ $profile->group_code ?? '—' }}</td>
                    <td>
                        @if ($profile->is_rentable)
                            <x-status-badge size="md" outline tone="success">{{ __('leihfähig') }}</x-status-badge>
                        @else
                            <x-status-badge size="md" outline>{{ __('gesperrt für Verleih') }}</x-status-badge>
                        @endif
                    </td>
                    <td>{{ $profile->buffer_before_hours }} h / {{ $profile->buffer_after_hours }} h</td>
                    <td>{{ $profile->requires_inspection ? __('ja (blockt bei Überfälligkeit)') : __('nein') }}</td>
                    <td>
                        @if ($profile->asset !== null && $profile->asset->activeBlocks->isNotEmpty())
                            <span class="badge badge-error badge-outline">{{ __('Gesperrt: :reason', ['reason' => $profile->asset->activeBlocks->first()->reason->label()]) }}</span>
                        @else
                            —
                        @endif
                    </td>
                    <td>{{ $profile->defaultRateCard !== null ? $profile->defaultRateCard->name . ' (v' . $profile->defaultRateCard->version . ')' : '—' }}</td>
                </tr>
            @empty
                <x-table.empty :colspan="7" :title="__('Noch keine Verleihprofile — unten ein Asset leihfähig machen.')" compact />
            @endforelse
        </x-table>
    </x-card>

    <x-pagination :paginator="$profiles" standing />

    @can('create', \App\Models\Rental\RentalCase::class)
        <x-card :title="__('Asset leihfähig machen / Profil pflegen')">
            <form method="POST" action="{{ route('rental.profiles.store') }}" class="grid gap-3 sm:grid-cols-2">
                @csrf
                <x-select-field name="asset_id" :label="__('Asset')" required>
                    @foreach ($assets as $a)
                        <option value="{{ $a->sqid }}">{{ $a->name }}</option>
                    @endforeach
                </x-select-field>
                <x-input-field name="group_code" :label="__('Gerätegruppe (Code)')" :value="old('group_code')" />
                <x-input-field name="buffer_before_hours" type="number" min="0" max="720" :label="__('Puffer vor Verleih (Stunden)')" value="0" />
                <x-input-field name="buffer_after_hours" type="number" min="0" max="720" :label="__('Puffer nach Verleih (Stunden, z. B. Reinigung)')" value="0" />
                <x-select-field name="default_rate_card_id" :label="__('Standard-Preisliste')">
                    <option value="">{{ __('keine') }}</option>
                    @foreach ($rateCards as $card)
                        <option value="{{ $card->sqid }}">{{ $card->name }} (v{{ $card->version }})</option>
                    @endforeach
                </x-select-field>
                <div class="flex flex-col justify-end gap-1">
                    <label class="label cursor-pointer justify-start gap-2">
                        <input type="hidden" name="is_rentable" value="0">
                        <input type="checkbox" name="is_rentable" value="1" class="checkbox checkbox-sm" checked>
                        <span class="label-text text-sm">{{ __('leihfähig') }}</span>
                    </label>
                    <label class="label cursor-pointer justify-start gap-2">
                        <input type="hidden" name="requires_inspection" value="0">
                        <input type="checkbox" name="requires_inspection" value="1" class="checkbox checkbox-sm">
                        <span class="label-text text-sm">{{ __('Prüfpflicht: überfällige Prüfung blockiert Verleih') }}</span>
                    </label>
                </div>
                <x-textarea-field name="accessories" :label="__('Zubehör (eine Position je Zeile)')" rows="3">{{ old('accessories') }}</x-textarea-field>
                <x-textarea-field name="notes" :label="__('Notizen')" rows="3">{{ old('notes') }}</x-textarea-field>
                <div class="sm:col-span-2">
                    <button type="submit" class="btn btn-sm btn-primary">{{ __('Profil speichern') }}</button>
                </div>
            </form>
        </x-card>
    @endcan
</x-index-page>
@endsection
