@extends('layouts.app')
@section('title', __('Schicht'))

@section('content')
    <div class="space-y-6">
        <x-page-title :title="__('Schicht am :date', ['date' => $shift->date->format('d.m.Y')])"
                      :subtitle="$shift->user?->name"
                      :badge="$shift->statusLabel()"
                      :badgeTone="$shift->statusTone()">
            <x-slot:actions>
                @can('update', $shift)
                    <a href="{{ route('scheduled-shifts.edit', $shift) }}" class="btn btn-primary btn-sm">{{ __('Bearbeiten') }}</a>
                @endcan
                <a href="{{ route('schedule.index') }}" class="btn btn-ghost btn-sm">{{ __('Zurück') }}</a>
            </x-slot:actions>
        </x-page-title>

        @if (session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif

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
                  onsubmit="return confirm('{{ __('Wirklich löschen?') }}')">
                @csrf @method('DELETE')
                <button type="submit" class="btn btn-error btn-sm">{{ __('Löschen') }}</button>
            </form>
        @endcan
    </div>
@endsection
