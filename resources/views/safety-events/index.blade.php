{{--
  Created on   : Mon Jun 15 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@extends('layouts.app')
@section('title', __('safety.title.index'))
@section('nav-title', __('safety.title.index'))
@section('wrapper-height-class', 'wd-page-fill')
@section('main-class', 'min-h-0 flex flex-col lg:overflow-clip')
@section('content')
<x-index-page overflow="clip" :subtitle="__('safety.subtitle.index')">
    <x-slot:actions>
        @if ($canCreate)
            <x-icon-btn icon="add" tone="primary" size="sm"
                        data-entry-modal-trigger
                        :href="route('safety-events.create')"
                        show-label>{{ __('safety.action.create') }}</x-icon-btn>
        @endif
    </x-slot:actions>

    <x-filter-bar :action="route('safety-events.index')" :reset="route('safety-events.index')">
        <x-filter-field :label="__('safety.field.kind')" for="flt-kind">
            <select id="flt-kind" name="kind" class="select select-sm select-bordered" data-autosubmit>
                <option value="">{{ __('Alle') }}</option>
                @foreach (\App\Enums\Safety\SafetyEventKind::cases() as $k)
                    <option value="{{ $k->value }}" @selected($kind === $k->value)>{{ $k->label() }}</option>
                @endforeach
            </select>
        </x-filter-field>
        <x-filter-field :label="__('safety.field.severity')" for="flt-sev">
            <select id="flt-sev" name="severity" class="select select-sm select-bordered" data-autosubmit>
                <option value="">{{ __('Alle') }}</option>
                @foreach (\App\Enums\Safety\SafetyEventSeverity::cases() as $s)
                    <option value="{{ $s->value }}" @selected($severity === $s->value)>{{ $s->label() }}</option>
                @endforeach
            </select>
        </x-filter-field>
        <x-filter-field :label="__('safety.field.status')" for="flt-status">
            <select id="flt-status" name="status" class="select select-sm select-bordered" data-autosubmit>
                <option value="">{{ __('Alle') }}</option>
                @foreach (\App\Enums\Safety\SafetyEventStatus::cases() as $st)
                    <option value="{{ $st->value }}" @selected($status === $st->value)>{{ $st->label() }}</option>
                @endforeach
            </select>
        </x-filter-field>
    </x-filter-bar>

    <x-table scroll="flex">
        <x-slot:head>
            <tr>
                <th>{{ __('safety.field.event_no') }}</th>
                <th>{{ __('safety.field.kind') }}</th>
                <th>{{ __('safety.field.severity') }}</th>
                <th>{{ __('safety.field.occurred_at') }}</th>
                <th>{{ __('safety.field.location') }}</th>
                <th>{{ __('safety.field.reporter') }}</th>
                <th>{{ __('safety.field.status') }}</th>
                <th></th>
            </tr>
        </x-slot:head>
        @forelse ($events as $event)
            <tr>
                <td class="font-mono text-sm">{{ $event->displayNo() }}</td>
                <td>
                    <span class="inline-flex items-center gap-1">
                        <x-icon :name="$event->kind->icon()" class="text-muted" />
                        {{ $event->kind->label() }}
                    </span>
                </td>
                <td><x-status-badge :tone="$event->severity->tone()" size="sm">{{ $event->severity->label() }}</x-status-badge></td>
                <td class="text-sm">{{ optional($event->occurred_at)->format('d.m.Y H:i') }}</td>
                <td class="text-sm text-base-content/70">{{ $event->location ?? '–' }}</td>
                <td class="text-sm">{{ $event->reporter?->name ?? '–' }}</td>
                <td><x-status-badge :tone="$event->status->tone()" size="sm">{{ $event->status->label() }}</x-status-badge></td>
                <td class="text-right">
                    <x-icon-btn icon="visibility" :href="route('safety-events.show', $event)" :label="__('safety.action.show')" />
                </td>
            </tr>
        @empty
            <x-table.empty icon="health_and_safety" :colspan="8" :title="__('safety.empty')" compact />
        @endforelse
    </x-table>
    <x-pagination :paginator="$events" standing />
</x-index-page>
@endsection
