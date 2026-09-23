{{--
  Created on   : Wed Sep 23 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Sportartenprofile (Feature 159, MVP-852): Sportart als Konfiguration — Familie, Positionen, Kadergrößen, Ergebnisformat. --}}
@extends('layouts.app')
@section('title', __('club.teams.title.profiles'))
@section('nav-title', __('club.teams.title.profiles'))
@section('wrapper-height-class', 'wd-page-fill')
@section('main-class', 'min-h-0 flex flex-col lg:overflow-clip')
@section('content')
<x-index-page overflow="clip" :subtitle="__('club.teams.subtitle.profiles')">
    <x-slot:actions>
        @if ($canManage && $packs !== [])
            <form method="POST" action="{{ route('club.profiles.packs.store') }}" class="flex items-center gap-2" title="{{ __('club.teams.hint.packs') }}">
                @csrf
                <label for="club-pack" class="sr-only">{{ __('club.teams.label.pack') }}</label>
                <select id="club-pack" name="pack" class="select select-sm select-bordered">
                    @foreach ($packs as $code => $label)
                        <option value="{{ $code }}" @selected(old('pack') === $code)>{{ $label }}</option>
                    @endforeach
                </select>
                <x-icon-btn type="submit" icon="inventory_2" tone="outline" size="sm" show-label>{{ __('club.teams.action.install_pack') }}</x-icon-btn>
            </form>
        @endif
        @if ($canManage)
            <x-icon-btn icon="add" tone="primary" size="sm" data-entry-modal-trigger :href="route('club.profiles.create')" show-label>{{ __('club.teams.action.create_profile') }}</x-icon-btn>
        @endif
        <x-help-button topic="club.matches" />
    </x-slot:actions>

    <x-table scroll="flex">
        <x-slot:head>
            <tr>
                <th>{{ __('club.field.name') }}</th>
                <th>{{ __('club.teams.field.family') }}</th>
                <th>{{ __('club.teams.field.result_format') }}</th>
                <th class="text-center">{{ __('club.teams.field.squad_sizes') }}</th>
                <th class="text-center">{{ __('club.teams.field.positions') }}</th>
                <th>{{ __('club.teams.field.age_cutoff') }}</th>
                <th class="text-right">{{ __('club.teams.field.usage') }}</th>
                <th>{{ __('club.field.status') }}</th>
                <th></th>
            </tr>
        </x-slot:head>
        @forelse ($profiles as $profile)
            <tr class="hover">
                <td class="font-medium"><x-icon :name="$profile->family->icon()" class="mr-1 text-muted" />{{ $profile->name }}</td>
                <td class="text-sm">{{ $profile->family->label() }}@if ($profile->has_doubles) <span class="badge badge-ghost badge-xs">{{ __('club.teams.label.doubles') }}</span>@endif</td>
                <td class="text-sm">{{ $profile->result_format->label() }}</td>
                <td class="text-center text-sm tabular-nums">{{ $profile->squad_size_field ?? '–' }} / {{ $profile->squad_size_bench ?? '–' }}</td>
                <td class="text-center text-sm tabular-nums">{{ count($profile->positions ?? []) }}</td>
                <td class="text-sm">{{ $profile->age_cutoff ?? __('club.teams.label.age_on_day') }}</td>
                <td class="text-right text-sm tabular-nums">{{ $profile->groups_count + $profile->departments_count }}</td>
                <td><x-status-badge :tone="$profile->is_active ? 'success' : 'ghost'" size="sm">{{ $profile->is_active ? __('club.grading.label.active') : __('club.label.inactive') }}</x-status-badge></td>
                <td class="text-right">
                    @if ($canManage)
                        <x-icon-btn icon="edit" tone="ghost" size="xs" data-entry-modal-trigger :href="route('club.profiles.edit', $profile)" :label="__('club.action.edit')" />
                        @if ($profile->groups_count + $profile->departments_count === 0)
                            <x-action-form :action="route('club.profiles.destroy', $profile)" method="DELETE" :confirm="__('club.teams.confirm.delete_profile', ['name' => $profile->name])" confirm-icon="delete" confirm-tone="error" class="inline">
                                <x-icon-btn type="submit" icon="delete" tone="ghost" size="xs" class="text-error" :label="__('club.action.delete')" />
                            </x-action-form>
                        @endif
                    @endif
                </td>
            </tr>
        @empty
            <x-table.empty icon="sports" :colspan="9" :title="__('club.teams.empty.profiles')" :message="__('club.teams.hint.profiles_empty')" compact />
        @endforelse
    </x-table>
</x-index-page>
@endsection
