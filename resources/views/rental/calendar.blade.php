@extends('layouts.app')

@section('title', __('Verfügbarkeitskalender'))
@section('nav-title', __('Verfügbarkeit'))

@section('content')
<x-index-page :subtitle="__('Belegungsfenster je Gerät: Reservierung, Verleih, Wartung, Reinigung und Transport — inklusive Pufferzeiten.')">
    <x-slot:actions>
        <x-icon-btn icon="chevron_left" size="sm" :href="route('rental.calendar', array_merge(request()->query(), ['month' => $month->copy()->subMonth()->format('Y-m')]))" :label="__('Vormonat')" />
        <span class="font-medium">{{ $month->translatedFormat('F Y') }}</span>
        <x-icon-btn icon="chevron_right" size="sm" :href="route('rental.calendar', array_merge(request()->query(), ['month' => $month->copy()->addMonth()->format('Y-m')]))" :label="__('Folgemonat')" />
    </x-slot:actions>

    <x-filter-bar :action="route('rental.calendar')" :reset="route('rental.calendar')">
        <input type="hidden" name="month" value="{{ $month->format('Y-m') }}">
        <select name="asset_id" class="select select-sm select-bordered w-52 shrink-0" aria-label="{{ __('Gerät') }}">
            <option value="">{{ __('Alle Geräte') }}</option>
            @foreach ($assets as $a)
                <option value="{{ $a->sqid }}" @selected($filterAsset === $a->id)>{{ $a->name }}</option>
            @endforeach
        </select>
        <select name="group" class="select select-sm select-bordered w-44 shrink-0" aria-label="{{ __('Gerätegruppe') }}">
            <option value="">{{ __('Alle Gruppen') }}</option>
            @foreach ($groups as $group)
                <option value="{{ $group }}" @selected($filterGroup === $group)>{{ $group }}</option>
            @endforeach
        </select>
    </x-filter-bar>

    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif
    @if ($errors->any())
        <div class="alert alert-error text-sm">
            <ul class="list-disc pl-5">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
        </div>
    @endif

    <x-month-calendar :month="$month" :items-by-day="$itemsByDay" item-view="rental.partials._calendar_day" />

    @can('create', \App\Models\Rental\RentalCase::class)
        <x-card :title="__('Belegungsfenster eintragen (Wartung/Reinigung/Transport/Vormerkung)')">
            <form method="POST" action="{{ route('rental.reservations.store') }}" class="flex flex-wrap items-end gap-2">
                @csrf
                <x-select-field name="asset_id" :label="__('Gerät')" required>
                    @foreach ($assets as $a)
                        <option value="{{ $a->sqid }}">{{ $a->name }}</option>
                    @endforeach
                </x-select-field>
                <x-select-field name="kind" :label="__('Art')" required>
                    @foreach (\App\Enums\Rental\RentalReservationKind::cases() as $kind)
                        @if (! in_array($kind, [\App\Enums\Rental\RentalReservationKind::Rental, \App\Enums\Rental\RentalReservationKind::Hard], true))
                            <option value="{{ $kind->value }}">{{ $kind->label() }}</option>
                        @endif
                    @endforeach
                </x-select-field>
                <x-input-field name="starts_at" type="datetime-local" :label="__('Beginn')" required />
                <x-input-field name="ends_at" type="datetime-local" :label="__('Ende')" required />
                <x-input-field name="note" :label="__('Notiz')" />
                <button type="submit" class="btn btn-sm btn-primary">{{ __('Eintragen') }}</button>
            </form>
        </x-card>
    @endcan
</x-index-page>
@endsection
