{{--
  Created on   : Wed Sep 23 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : show.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{--
  Detailseite Ressource (MVP-853): Stammdaten und Baum, kommende Belegungen (markierte zur Neuplanung),
  Sperrzeiten, Freigaben. Variablen: $resource, $bookings, $closures, $clearances, $assetBlocked, $canManage, $canClear, $today.
--}}
@extends('layouts.app')
@section('title', $resource->fullName())
@section('nav-title', $resource->name)
@section('content')
<x-page-shell>
    <x-slot:toolbar>
        <x-page-toolbar :subtitle="$resource->kind->label() . ' · ' . __('club.resources.label.capacity_of', ['count' => $resource->capacity]) . ($resource->room ? ' · ' . $resource->room->name : '') . ($resource->asset ? ' · ' . $resource->asset->name : '')"
                        :badge="$assetBlocked ? __('club.resources.label.asset_blocked') : ($resource->is_active ? __('club.grading.label.active') : __('club.label.inactive'))"
                        :badgeTone="$assetBlocked ? 'error' : ($resource->is_active ? 'success' : 'ghost')">
            <x-slot:actions>
                @if ($canClear)
                    <x-icon-btn icon="verified_user" tone="outline" size="sm" data-entry-modal-trigger :href="route('club.resources.clearances.create', $resource)" show-label>{{ __('club.resources.action.grant_clearance') }}</x-icon-btn>
                @endif
                @if ($canManage)
                    <x-icon-btn icon="block" tone="outline" size="sm" class="btn-warning" data-entry-modal-trigger :href="route('club.resources.closures.create', $resource)" show-label>{{ __('club.resources.action.close') }}</x-icon-btn>
                    <x-icon-btn icon="edit" tone="outline" size="sm" data-entry-modal-trigger :href="route('club.resources.edit', $resource)" show-label>{{ __('club.action.edit') }}</x-icon-btn>
                @endif
                <x-icon-btn icon="arrow_back" tone="ghost" size="sm" :href="route('club.resources.index')" show-label>{{ __('club.action.back') }}</x-icon-btn>
            </x-slot:actions>
        </x-page-toolbar>
    </x-slot:toolbar>

    <div class="grid gap-4 lg:grid-cols-3">
        <div class="space-y-4 lg:col-span-2">
            <x-card :title="__('club.resources.card.bookings')" icon="event" :count="$bookings->count()">
                <p class="mb-2 text-xs text-muted">{{ __('club.resources.hint.bookings') }}</p>
                <x-table :bare="true" size="sm">
                    <x-slot:head>
                        <tr><th>{{ __('club.events.field.starts') }}</th><th>{{ __('club.events.field.title') }}</th><th class="text-center">{{ __('club.resources.field.quantity') }}</th><th>{{ __('club.field.member') }}</th><th>{{ __('club.field.status') }}</th></tr>
                    </x-slot:head>
                    @forelse ($bookings as $booking)
                        <tr class="{{ $booking->event?->cancelled_at ? 'opacity-60' : '' }}">
                            <td class="whitespace-nowrap text-sm tabular-nums">{{ $booking->starts_at->orgTz()->format('d.m.Y H:i') }}–{{ $booking->ends_at->orgTz()->format('H:i') }}@if ($booking->setup_minutes || $booking->teardown_minutes)<span class="block text-xs text-muted">{{ __('club.resources.label.buffers', ['setup' => $booking->setup_minutes, 'teardown' => $booking->teardown_minutes]) }}</span>@endif</td>
                            <td class="text-sm">@if ($booking->event)<a href="{{ route('club.events.show', $booking->event) }}" class="link link-hover">{{ $booking->event->title }}</a>@endif</td>
                            <td class="text-center text-sm tabular-nums">{{ $booking->quantity }}</td>
                            <td class="text-sm">{{ $booking->member?->fullName() ?? '–' }}</td>
                            <td>
                                @if ($booking->isFlagged())
                                    <x-status-badge tone="warning" size="xs" icon="warning" :label="$booking->flag_reason ?? __('club.resources.label.replan')" />
                                @elseif ($booking->event?->cancelled_at)
                                    <x-status-badge tone="ghost" size="xs">{{ __('club.events.label.cancelled') }}</x-status-badge>
                                @else
                                    <x-status-badge tone="success" size="xs">{{ __('club.resources.label.booked') }}</x-status-badge>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <x-table.empty icon="event" :colspan="5" :title="__('club.resources.empty.bookings')" compact />
                    @endforelse
                </x-table>
            </x-card>

            <x-card :title="__('club.resources.card.closures')" icon="block" :count="$closures->count()">
                <ul class="space-y-1 text-sm">
                    @forelse ($closures as $closure)
                        <li class="flex flex-wrap items-center gap-2">
                            <span class="tabular-nums">{{ $closure->starts_at->orgTz()->format('d.m.Y H:i') }} – {{ $closure->ends_at->orgTz()->format('d.m.Y H:i') }}</span>
                            <span>{{ $closure->reason }}</span>
                            <span class="text-xs text-muted">{{ $closure->createdBy?->name }}</span>
                            @if ($canManage)
                                <x-action-form :action="route('club.resources.closures.destroy', [$resource, $closure])" :confirm="__('club.resources.confirm.reopen')" confirm-icon="lock_open" confirm-tone="primary" class="ml-auto">
                                    <x-icon-btn type="submit" icon="lock_open" tone="ghost" size="xs" show-label>{{ __('club.resources.action.reopen') }}</x-icon-btn>
                                </x-action-form>
                            @endif
                        </li>
                    @empty
                        <li class="text-muted">{{ __('club.resources.empty.closures') }}</li>
                    @endforelse
                </ul>
                <p class="mt-2 text-xs text-muted">{{ __('club.resources.hint.closure') }}</p>
            </x-card>
        </div>

        <div class="space-y-4">
            <x-card :title="__('club.resources.card.details')" icon="info">
                <x-detail-grid>
                    <x-detail-grid.row :label="__('club.resources.field.kind')" :value="$resource->kind->label()" />
                    <x-detail-grid.row :label="__('club.resources.field.parent')">
                        @if ($resource->parent)<a href="{{ route('club.resources.show', $resource->parent) }}" class="link link-hover">{{ $resource->parent->fullName() }}</a>@else –@endif
                    </x-detail-grid.row>
                    <x-detail-grid.row :label="__('club.resources.field.children')">
                        @forelse ($resource->children as $child)
                            <a href="{{ route('club.resources.show', $child) }}" class="badge badge-ghost badge-sm">{{ $child->name }}</a>
                        @empty
                            –
                        @endforelse
                    </x-detail-grid.row>
                    <x-detail-grid.row :label="__('club.resources.field.capacity')" :value="(string) $resource->capacity" />
                    <x-detail-grid.row :label="__('club.resources.field.setup_minutes')" :value="$resource->setup_minutes . ' / ' . $resource->teardown_minutes" />
                    <x-detail-grid.row :label="__('club.resources.field.room')" :value="$resource->room?->name ?? '–'" />
                    <x-detail-grid.row :label="__('club.resources.field.asset')" :value="$resource->asset ? $resource->asset->name . ' (' . $resource->asset->asset_no . ')' : '–'" />
                    <x-detail-grid.row :label="__('club.resources.field.requires_clearance')" :value="$resource->requires_clearance ? __('club.label.yes') : __('club.label.no')" />
                    @if ($resource->notes)
                        <x-detail-grid.row :label="__('club.field.notes')" :value="$resource->notes" />
                    @endif
                </x-detail-grid>
                @if ($assetBlocked)
                    <p class="mt-2 text-sm text-error">{{ __('club.resources.hint.asset_blocked') }}</p>
                @endif
                @if ($canManage && $resource->children->isEmpty())
                    <x-action-form :action="route('club.resources.destroy', $resource)" method="DELETE" :confirm="__('club.resources.confirm.delete', ['name' => $resource->name])" confirm-icon="delete" confirm-tone="error" class="mt-3">
                        <x-icon-btn type="submit" icon="delete" tone="ghost" size="xs" class="text-error" show-label>{{ __('club.action.delete') }}</x-icon-btn>
                    </x-action-form>
                @endif
            </x-card>

            <x-card :title="__('club.resources.card.clearances')" icon="verified_user" :count="$clearances->count()">
                <ul class="space-y-1 text-sm">
                    @forelse ($clearances as $clearance)
                        <li class="flex flex-wrap items-center gap-2 {{ $clearance->isValidOn($today) ? '' : 'opacity-60' }}">
                            @if ($clearance->member)<a href="{{ route('club.members.show', $clearance->member) }}" class="link link-hover">{{ $clearance->member->fullName() }}</a>@endif
                            <span class="text-xs text-muted">{{ $clearance->granted_on->format('d.m.Y') }}@if ($clearance->valid_to) – {{ $clearance->valid_to->format('d.m.Y') }}@endif</span>
                            @if ($clearance->note)<span class="text-xs text-muted">{{ $clearance->note }}</span>@endif
                            @if ($canClear)
                                <x-action-form :action="route('club.resources.clearances.destroy', [$resource, $clearance])" class="ml-auto">
                                    <x-icon-btn type="submit" icon="close" tone="ghost" size="xs" :label="__('club.resources.action.revoke_clearance')" />
                                </x-action-form>
                            @endif
                        </li>
                    @empty
                        <li class="text-muted">{{ $resource->requires_clearance ? __('club.resources.empty.clearances') : __('club.resources.label.no_clearance_needed') }}</li>
                    @endforelse
                </ul>
            </x-card>
        </div>
    </div>
</x-page-shell>
@endsection
