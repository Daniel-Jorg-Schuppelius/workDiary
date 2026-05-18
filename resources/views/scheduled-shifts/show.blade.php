@extends('layouts.app')
@section('title', __('Schicht'))
@section('nav-title', __('Schicht am :date', ['date' => $shift->date->format('d.m.Y')]))

@section('content')
    <x-page-shell gap="6">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <div class="flex flex-wrap items-center gap-2 text-sm text-base-content/70">
                @if ($shift->user)
                    <span>{{ $shift->user->name }}</span>
                    <span>·</span>
                @endif
                <span class="badge badge-sm badge-{{ $shift->statusTone() }}">{{ $shift->statusLabel() }}</span>
            </div>
            <div class="flex items-center gap-2">
                @can('update', $shift)
                    <a href="{{ route('scheduled-shifts.edit', $shift) }}" class="btn btn-primary btn-sm">{{ __('Bearbeiten') }}</a>
                @endcan
                <a href="{{ route('schedule.index') }}" class="btn btn-ghost btn-sm">{{ __('Zurück') }}</a>
            </div>
        </div>

        <div class="card border border-base-300 bg-base-100 shadow-xs max-w-2xl">
            <div class="card-body grid gap-3 sm:grid-cols-2">
                <div><span class="text-xs uppercase text-base-content/60">{{ __('Mitarbeiter') }}</span><div>{{ $shift->user?->name ?? '—' }}</div></div>
                <div><span class="text-xs uppercase text-base-content/60">{{ __('Datum') }}</span><div>{{ $shift->date->format('d.m.Y') }}</div></div>
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
            <form action="{{ route('scheduled-shifts.destroy', $shift) }}" method="POST"
                  data-confirm-dialog
                  data-confirm-message="{{ __('Wirklich löschen?') }}"
                  data-confirm-label="{{ __('Löschen') }}">
                @csrf @method('DELETE')
                <button type="submit" class="btn btn-error btn-sm">{{ __('Löschen') }}</button>
            </form>
        @endcan
    </x-page-shell>
@endsection
