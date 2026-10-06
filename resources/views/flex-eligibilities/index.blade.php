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
@include('partials.page-fill')

@section('content')
<x-page-shell overflow="clip">
    <x-slot:toolbar>
        <x-page-toolbar :title="$member->name"
                        :subtitle="__('flex.eligibility.subtitle', ['name' => $member->name])">
            <x-slot:badges>
                @if ($isCurrentlyEligible)
                    <x-status-badge tone="success" size="md" class="gap-2">
                        <x-icon name="schedule" />
                        {{ __('flex.eligibility.current.active') }}
                    </x-status-badge>
                @else
                    <x-status-badge tone="ghost" size="md" class="gap-2">
                        <x-icon name="history_toggle_off" />
                        {{ __('flex.eligibility.current.inactive') }}
                    </x-status-badge>
                @endif
            </x-slot:badges>
        </x-page-toolbar>
    </x-slot:toolbar>

    <x-card class="flex flex-col gap-2">
        <h2 class="card-title text-base">{{ __('flex.eligibility.form.add_title') }}</h2>

        <form method="POST" action="{{ route('users.flex-eligibility.store', $member) }}"
              class="grid grid-cols-1 md:grid-cols-4 gap-3 items-end mt-2">
            @csrf
            <x-date-range class="md:col-span-2" layout="split" form-control size="md"
                          from-name="valid_from" to-name="valid_to" from-required
                          :from="old('valid_from', now()->toDateString())" :to="old('valid_to')"
                          :from-label="__('flex.eligibility.form.valid_from')"
                          :to-label="__('flex.eligibility.form.valid_to')" />
            <x-form-group :label="__('flex.eligibility.form.note')" name="note" class="md:col-span-2">
                <input type="text" name="note" value="{{ old('note') }}" maxlength="500"
                       class="input input-bordered w-full" />
            </x-form-group>
            <div class="md:col-span-4 flex justify-end">
                <x-button type="submit" tone="primary">
                    {{ __('flex.eligibility.form.submit') }}
                </x-button>
            </div>
        </form>
    </x-card>

    <x-table scroll="flex" :pinRows="true" table-sort="server"
             :route="route('users.flex-eligibility.index', $member)"
             :current-sort="$sort"
             :current-dir="$dir">
        <x-slot:head>
            <tr>
                <x-table.th sort="valid_from" default>{{ __('flex.eligibility.table.valid_from') }}</x-table.th>
                <x-table.th sort="valid_to">{{ __('flex.eligibility.table.valid_to') }}</x-table.th>
                <x-table.th sort="note">{{ __('flex.eligibility.table.note') }}</x-table.th>
                <th class="text-right">{{ __('flex.eligibility.table.actions') }}</th>
            </tr>
        </x-slot:head>
        @forelse ($periods as $period)
            <tr class="hover">
                <td>{{ $period->valid_from->fdate() }}</td>
                <td>
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
                            <x-button type="submit" tone="ghost" size="xs"
                                    title="{{ __('flex.eligibility.form.end_today') }}" icon="event_busy">{{ __('flex.eligibility.form.end_submit') }}</x-button>
                        </form>
                    @endif

                    <form method="POST" action="{{ route('users.flex-eligibility.destroy', [$member, $period]) }}" class="inline">
                        @csrf @method('DELETE')
                        <x-icon-btn type="submit" icon="delete" size="xs" tone="error"
                                    data-confirm-dialog data-confirm-message="{{ __('flex.eligibility.confirm_delete') }}" data-confirm-tone="error" />
                    </form>
                </td>
            </tr>
        @empty
            <x-table.empty :colspan="4"
                icon="rule"
                :title="__('flex.eligibility.empty', ['name' => $member->name])" compact />
        @endforelse
    </x-table>

    <x-pagination :paginator="$periods" standing />
</x-page-shell>
@endsection
