{{--
  Created on   : Sat Aug 29 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : show.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Stationen eines Lernpfads. Zugewiesen wird über die reguläre
  Einschreibung — ein eigener Zuweisungsweg wäre eine zweite Wahrheit über
  den Lernstand.
--}}
@extends('layouts.app')
@section('title', $path->title)
@section('nav-title', $path->title)
@section('content')
<x-page-shell>
    <x-slot:toolbar>
        <x-page-toolbar :subtitle="$path->code">
            <x-slot:actions>
                <x-icon-btn icon="arrow_back" tone="ghost" size="sm"
                            :href="route('learning.paths.index')"
                            show-label>{{ __('learning.action.back') }}</x-icon-btn>
            </x-slot:actions>
        </x-page-toolbar>
    </x-slot:toolbar>

    <div class="grid gap-4 lg:grid-cols-3">
        <div class="space-y-4 lg:col-span-2">
            <x-card>
                <h3 class="mb-3 text-sm font-semibold">{{ __('learning.field.stations') }}</h3>
                <x-table bare>
                    <x-slot:head>
                        <tr>
                            <th class="w-12">#</th>
                            <th>{{ __('learning.field.course') }}</th>
                            <th class="w-28">{{ __('learning.field.due_days') }}</th>
                            <th class="w-16"></th>
                        </tr>
                    </x-slot:head>
                    @forelse ($path->items->sortBy('position') as $item)
                        <tr>
                            <td>{{ $item->position }}</td>
                            <td>{{ $item->course?->title ?? '—' }}</td>
                            <td>{{ $item->due_days ?? '—' }}</td>
                            <td class="text-right">
                                <form method="POST" action="{{ route('learning.paths.items.destroy', [$path->sqid, $item->sqid]) }}">
                                    @csrf
                                    @method('DELETE')
                                    <x-icon-btn icon="delete" tone="ghost" size="xs" type="submit"
                                                :label="__('learning.action.remove_station')" />
                                </form>
                            </td>
                        </tr>
                    @empty
                        <x-table.empty icon="route" :message="__('learning.empty.stations')" colspan="4" compact />
                    @endforelse
                </x-table>

                <form method="POST" action="{{ route('learning.paths.items.store', $path->sqid) }}" class="mt-3">
                    @csrf
                    <x-form-group :legend="__('learning.action.add_station')" icon="add_road" tone="primary" cols="2">
                        <x-select-field name="learning_course_id" :label="__('learning.field.course')" required>
                            @foreach ($courses as $course)
                                <option value="{{ $course->sqid }}">{{ $course->title }}</option>
                            @endforeach
                        </x-select-field>
                        <x-input-field name="due_days" type="number" min="1" max="3650"
                                       :label="__('learning.field.due_days')"
                                       :hint="__('learning.help.due_days')" />
                    </x-form-group>
                    <div class="mt-2 flex justify-end">
                        <x-icon-btn icon="add" tone="primary" size="sm" type="submit" show-label>{{ __('learning.action.add_station') }}</x-icon-btn>
                    </div>
                </form>
            </x-card>
        </div>

        <div class="space-y-4">
            <x-card>
                <h3 class="mb-3 text-sm font-semibold">{{ __('learning.action.assign_path') }}</h3>
                <form method="POST" action="{{ route('learning.paths.assign', $path->sqid) }}">
                    @csrf
                    {{-- Sqid statt roher ID (Konvention). --}}
                    <x-user-select name="user_id" :users="$users" value-key="sqid" required />
                    <div class="mt-3 flex justify-end">
                        <x-icon-btn icon="person_add" tone="primary" size="sm" type="submit" show-label>{{ __('learning.action.assign_path') }}</x-icon-btn>
                    </div>
                </form>
                <p class="mt-2 text-xs text-muted">{{ __('learning.help.assign_path') }}</p>
            </x-card>
        </div>
    </div>
</x-page-shell>
@endsection
