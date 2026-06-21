@extends('layouts.app')
@section('title', __('Schicht'))
@section('nav-title', __('Schicht am :date', ['date' => $shift->date->fdate()]))

@section('content')
    <x-page-shell>
        <x-page-toolbar :badge="$shift->statusLabel()" :badge-tone="$shift->statusTone()">
            @if ($shift->user)
                <span>{{ $shift->user->name }}</span>
            @endif
            <x-slot:actions>
                <x-icon-btn icon="arrow_back" size="sm"
                            :href="route('schedule.index')"
                            show-label>{{ __('Zurück') }}</x-icon-btn>
                @can('update', $shift)
                    <x-icon-btn icon="edit" tone="primary" size="sm"
                                data-entry-modal-trigger
                                :href="route('scheduled-shifts.edit', $shift)"
                                show-label>{{ __('Bearbeiten') }}</x-icon-btn>
                @endcan
            </x-slot:actions>
        </x-page-toolbar>

        <div class="card border border-base-300 bg-base-100 shadow-xs max-w-2xl">
            <div class="card-body grid gap-3 sm:grid-cols-2">
                <div><span class="text-xs uppercase text-base-content/60">{{ __('Mitarbeiter') }}</span><div>{{ $shift->user?->name ?? '—' }}</div></div>
                <div><span class="text-xs uppercase text-base-content/60">{{ __('Datum') }}</span><div>{{ $shift->date->fdate() }}</div></div>
                <div><span class="text-xs uppercase text-base-content/60">{{ __('Beginn') }}</span><div>{{ $shift->start_time ?? '—' }}</div></div>
                <div><span class="text-xs uppercase text-base-content/60">{{ __('Ende') }}</span><div>{{ $shift->end_time ?? '—' }}</div></div>
                <div><span class="text-xs uppercase text-base-content/60">{{ __('Schichttyp') }}</span><div>{{ $shift->shiftType?->name ?? '—' }}</div></div>
                <div><span class="text-xs uppercase text-base-content/60">{{ __('Status') }}</span><div>{{ $shift->statusLabel() }}</div></div>
                @if ($shift->note)
                    <div class="sm:col-span-2"><span class="text-xs uppercase text-base-content/60">{{ __('Notiz') }}</span><div>{{ $shift->note }}</div></div>
                @endif
            </div>
        </div>

        @can('delete', $shift)
            <x-action-form :action="route('scheduled-shifts.destroy', $shift)" method="DELETE"
                  :confirm="__('Wirklich löschen?')"
                  :confirm-label="__('Löschen')">
                <x-icon-btn icon="delete" tone="error" size="sm" type="submit" show-label>{{ __('Löschen') }}</x-icon-btn>
            </x-action-form>
        @endcan
    </x-page-shell>
@endsection
