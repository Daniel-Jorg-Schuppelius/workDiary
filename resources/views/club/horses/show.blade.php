{{--
  Created on   : Wed Sep 23 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : show.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{--
  Detailseite Pferd (MVP-854): Profil, Einsätze und Zuordnungen, Sperrzeiten und Freigaben über die Ressource.
  Variablen: $horse, $resource, $assignments, $closures, $clearances, $minutesMonth, $recentUses, $canManage, $today.
--}}
@extends('layouts.app')
@section('title', $horse->name)
@section('nav-title', $horse->name)
@section('content')
<x-page-shell>
    <x-slot:toolbar>
        <x-page-toolbar :subtitle="$horse->kind->label() . ($horse->owner ? ' · ' . $horse->owner->fullName() : '') . ($horse->suitable_for ? ' · ' . $horse->suitable_for : '')"
                        :badge="$horse->is_active ? __('club.grading.label.active') : __('club.label.inactive')"
                        :badgeTone="$horse->is_active ? 'success' : 'ghost'">
            <x-slot:actions>
                <x-icon-btn icon="stadium" tone="outline" size="sm" :href="route('club.resources.show', $resource)" show-label>{{ __('club.horses.action.resource') }}</x-icon-btn>
                @if ($canManage)
                    <x-icon-btn icon="block" tone="outline" size="sm" class="btn-warning" data-entry-modal-trigger :href="route('club.resources.closures.create', $resource)" show-label>{{ __('club.horses.action.close') }}</x-icon-btn>
                    <x-icon-btn icon="verified_user" tone="outline" size="sm" data-entry-modal-trigger :href="route('club.resources.clearances.create', $resource)" show-label>{{ __('club.horses.action.clearance') }}</x-icon-btn>
                    <x-icon-btn icon="edit" tone="outline" size="sm" data-entry-modal-trigger :href="route('club.horses.edit', $horse)" show-label>{{ __('club.action.edit') }}</x-icon-btn>
                @endif
                <x-icon-btn icon="arrow_back" tone="ghost" size="sm" :href="route('club.horses.index')" show-label>{{ __('club.action.back') }}</x-icon-btn>
            </x-slot:actions>
        </x-page-toolbar>
    </x-slot:toolbar>

    <div class="grid gap-4 lg:grid-cols-3">
        <div class="space-y-4 lg:col-span-2">
            <x-card :title="__('club.horses.card.assignments')" icon="event" :count="$assignments->count()">
                <x-table :bare="true" size="sm">
                    <x-slot:head>
                        <tr><th>{{ __('club.events.field.starts') }}</th><th>{{ __('club.events.field.title') }}</th><th>{{ __('club.horses.field.rider') }}</th><th>{{ __('club.field.status') }}</th></tr>
                    </x-slot:head>
                    @forelse ($assignments as $assignment)
                        <tr class="{{ $assignment->event?->cancelled_at ? 'opacity-60' : '' }}">
                            <td class="whitespace-nowrap text-sm tabular-nums">{{ $assignment->event?->started_at->orgTz()->format('d.m.Y H:i') }}</td>
                            <td class="text-sm">@if ($assignment->event)<a href="{{ route('club.events.show', $assignment->event) }}" class="link link-hover">{{ $assignment->event->title }}</a>@endif</td>
                            <td class="text-sm">{{ $assignment->member?->fullName() }}@if ($assignment->override_note)<span class="block text-xs text-warning">{{ __('club.horses.label.override', ['note' => $assignment->override_note]) }}</span>@endif</td>
                            <td>@if ($assignment->needsReview())<x-status-badge tone="warning" size="xs" icon="warning" :label="$assignment->review_reason" />@elseif ($assignment->event?->cancelled_at)<x-status-badge tone="ghost" size="xs">{{ __('club.events.label.cancelled') }}</x-status-badge>@else<x-status-badge tone="success" size="xs">{{ __('club.horses.label.assigned') }}</x-status-badge>@endif</td>
                        </tr>
                    @empty
                        <x-table.empty icon="event" :colspan="4" :title="__('club.horses.empty.assignments')" compact />
                    @endforelse
                </x-table>
            </x-card>

            <x-card :title="__('club.horses.card.uses')" icon="timer" :count="$recentUses->count()">
                <p class="mb-2 text-sm">{{ __('club.horses.label.minutes_month', ['minutes' => $minutesMonth]) }}</p>
                <ul class="space-y-1 text-sm">
                    @forelse ($recentUses as $use)
                        <li class="flex flex-wrap items-center gap-2">
                            <span class="tabular-nums">{{ $use->event?->started_at->orgTz()->format('d.m.Y') }}</span>
                            <span>{{ $use->event?->title }}</span>
                            <span class="text-xs text-muted">{{ $use->member?->fullName() ?? '–' }}</span>
                            <span class="ml-auto tabular-nums">{{ $use->minutes }} min</span>
                        </li>
                    @empty
                        <li class="text-muted">{{ __('club.horses.empty.uses') }}</li>
                    @endforelse
                </ul>
                <p class="mt-2 text-xs text-muted">{{ __('club.horses.hint.uses') }}</p>
            </x-card>
        </div>

        <div class="space-y-4">
            <x-card :title="__('club.horses.card.profile')" icon="info">
                <x-detail-grid>
                    <x-detail-grid.row :label="__('club.horses.field.kind')" :value="$horse->kind->label()" />
                    <x-detail-grid.row :label="__('club.horses.field.owner')" :value="$horse->owner?->fullName() ?? '–'" />
                    <x-detail-grid.row :label="__('club.horses.field.contact')" :value="$horse->contact ?? '–'" />
                    <x-detail-grid.row :label="__('club.horses.field.groups')" :value="$horse->groups->pluck('name')->implode(', ') ?: __('club.horses.label.all_groups')" />
                    <x-detail-grid.row :label="__('club.horses.field.suitable_for')" :value="$horse->suitable_for ?? '–'" />
                    <x-detail-grid.row :label="__('club.horses.field.max_uses_per_day')" :value="$horse->max_uses_per_day !== null ? (string) $horse->max_uses_per_day : __('club.label.unlimited')" />
                    <x-detail-grid.row :label="__('club.horses.field.rest_minutes')" :value="(string) $resource->teardown_minutes" />
                    <x-detail-grid.row :label="__('club.horses.field.requires_clearance')" :value="$resource->requires_clearance ? __('club.label.yes') : __('club.label.no')" />
                    @if ($horse->notes)
                        <x-detail-grid.row :label="__('club.field.notes')" :value="$horse->notes" />
                    @endif
                </x-detail-grid>
                <p class="mt-3 text-xs text-muted">{{ __('club.horses.hint.no_automatic_fitness') }}</p>
                @if ($canManage && $assignments->isEmpty())
                    <x-action-form :action="route('club.horses.destroy', $horse)" method="DELETE" :confirm="__('club.horses.confirm.delete', ['name' => $horse->name])" confirm-icon="delete" confirm-tone="error" class="mt-3">
                        <x-icon-btn type="submit" icon="delete" tone="ghost" size="xs" class="text-error" show-label>{{ __('club.action.delete') }}</x-icon-btn>
                    </x-action-form>
                @endif
            </x-card>

            <x-card :title="__('club.resources.card.closures')" icon="block" :count="$closures->count()">
                <ul class="space-y-1 text-sm">
                    @forelse ($closures as $closure)
                        <li><span class="tabular-nums">{{ $closure->starts_at->orgTz()->format('d.m.Y H:i') }} – {{ $closure->ends_at->orgTz()->format('d.m.Y H:i') }}</span> {{ $closure->reason }}</li>
                    @empty
                        <li class="text-muted">{{ __('club.resources.empty.closures') }}</li>
                    @endforelse
                </ul>
                <p class="mt-2 text-xs text-muted">{{ __('club.horses.hint.closure') }}</p>
            </x-card>

            <x-card :title="__('club.horses.card.clearances')" icon="verified_user" :count="$clearances->count()">
                <ul class="space-y-1 text-sm">
                    @forelse ($clearances as $clearance)
                        <li class="{{ $clearance->isValidOn($today) ? '' : 'opacity-60' }}">{{ $clearance->member?->fullName() }} <span class="text-xs text-muted">{{ $clearance->granted_on->format('d.m.Y') }}@if ($clearance->valid_to) – {{ $clearance->valid_to->format('d.m.Y') }}@endif{{ $clearance->note ? ' · ' . $clearance->note : '' }}</span></li>
                    @empty
                        <li class="text-muted">{{ $resource->requires_clearance ? __('club.resources.empty.clearances') : __('club.resources.label.no_clearance_needed') }}</li>
                    @endforelse
                </ul>
            </x-card>
        </div>
    </div>
</x-page-shell>
@endsection
