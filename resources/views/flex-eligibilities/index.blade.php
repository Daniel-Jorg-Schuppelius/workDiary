{{--
  Created on   : Tue Jun 02 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}

@extends('layouts.app')

@section('title', __('flex.eligibility.title', ['name' => $member->name]))
@section('nav-title', __('flex.eligibility.nav_title'))
@section('wrapper-height-class', 'wd-page-fill')
@section('main-class', 'min-h-0 flex flex-col lg:overflow-clip')

@section('content')
<x-page-shell overflow="clip">
    <x-slot:toolbar>
        <x-page-toolbar :subtitle="__('Berechtigungen für Gleitzeit-Sonderregeln je Mitarbeiter pflegen.')">
            <x-slot:title>
                <h2 class="text-xl font-semibold">{{ $member->name }}</h2>
                <p class="text-sm text-base-content/60">{{ __('flex.eligibility.subtitle', ['name' => $member->name]) }}</p>
            </x-slot:title>
            <x-slot:actions>
                @if ($isCurrentlyEligible)
                    <x-status-badge tone="success" size="md" class="gap-2">
                        <x-icon name="schedule" />
                        {{ __('flex.eligibility.current.active') }}
                    </x-status-badge>
                @else
                    <x-status-badge tone="ghost" size="md" class="gap-2">
                        <x-icon name="schedule_off" />
                        {{ __('flex.eligibility.current.inactive') }}
                    </x-status-badge>
                @endif
            </x-slot:actions>
        </x-page-toolbar>
    </x-slot:toolbar>

    <div class="card bg-base-100 shadow-sm">
        <div class="card-body">
            <h2 class="card-title text-base">{{ __('flex.eligibility.form.add_title') }}</h2>

            <form method="POST" action="{{ route('users.flex-eligibility.store', $member) }}"
                  class="grid grid-cols-1 md:grid-cols-4 gap-3 items-end mt-2">
                @csrf
                <x-form-group :label="__('flex.eligibility.form.valid_from')" name="valid_from" required>
                    <input type="date" name="valid_from" value="{{ old('valid_from', now()->toDateString()) }}"
                           class="input input-bordered w-full" required />
                </x-form-group>
                <x-form-group :label="__('flex.eligibility.form.valid_to')" name="valid_to">
                    <input type="date" name="valid_to" value="{{ old('valid_to') }}"
                           class="input input-bordered w-full" />
                </x-form-group>
                <x-form-group :label="__('flex.eligibility.form.note')" name="note" class="md:col-span-2">
                    <input type="text" name="note" value="{{ old('note') }}" maxlength="500"
                           class="input input-bordered w-full" />
                </x-form-group>
                <div class="md:col-span-4 flex justify-end">
                    <button type="submit" class="btn btn-primary btn-sm">
                        {{ __('flex.eligibility.form.submit') }}
                    </button>
                </div>
            </form>
        </div>
    </div>

    <x-table scroll="flex" :pinRows="true" table-sort="client">
        <x-slot:head>
            <tr>
                <x-table.th sort type="date" default="desc">{{ __('flex.eligibility.table.valid_from') }}</x-table.th>
                <x-table.th sort type="date">{{ __('flex.eligibility.table.valid_to') }}</x-table.th>
                <x-table.th sort type="string">{{ __('flex.eligibility.table.note') }}</x-table.th>
                <th class="text-right">{{ __('flex.eligibility.table.actions') }}</th>
            </tr>
        </x-slot:head>
        @forelse ($periods as $period)
            <tr>
                <td data-sort-value="{{ $period->valid_from->format('Y-m-d') }}">{{ $period->valid_from->fdate() }}</td>
                <td data-sort-value="{{ $period->valid_to?->format('Y-m-d') ?? '9999-12-31' }}">
                    @if ($period->valid_to)
                        {{ $period->valid_to->fdate() }}
                    @else
                        <x-status-badge tone="ghost" size="sm">{{ __('flex.eligibility.table.open') }}</x-status-badge>
                    @endif
                </td>
                <td class="text-sm text-base-content/70">{{ $period->note }}</td>
                <td class="text-right">
                    @if (! $period->valid_to)
                        <form method="POST" action="{{ route('users.flex-eligibility.update', [$member, $period]) }}" class="inline">
                            @csrf @method('PUT')
                            <input type="hidden" name="valid_to" value="{{ now()->toDateString() }}" />
                            <input type="hidden" name="note" value="{{ $period->note }}" />
                            <button type="submit" class="btn btn-xs btn-ghost"
                                    title="{{ __('flex.eligibility.form.end_today') }}">
                                <x-icon name="event_busy" />
                                {{ __('flex.eligibility.form.end_submit') }}
                            </button>
                        </form>
                    @endif

                    <form method="POST" action="{{ route('users.flex-eligibility.destroy', [$member, $period]) }}" class="inline">
                        @csrf @method('DELETE')
                        <x-icon-btn type="submit" icon="delete" size="xs" tone="error"
                                    data-confirm="{{ __('flex.eligibility.confirm_delete') }}" />
                    </form>
                </td>
            </tr>
        @empty
            <x-table.empty :colspan="4"
                icon='<span class="material-symbols-outlined" aria-hidden="true">rule</span>'
                :title="__('flex.eligibility.empty', ['name' => $member->name])" compact />
        @endforelse
    </x-table>
</x-page-shell>
@endsection
