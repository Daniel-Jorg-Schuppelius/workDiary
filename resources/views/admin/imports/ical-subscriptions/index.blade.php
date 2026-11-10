{{--
  Created on   : Mon Nov 09 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@extends('layouts.app')

@section('title', __('import.ical_subscription.title'))
@section('nav-title', __('import.ical_subscription.title'))
@include('partials.page-fill')

@section('content')
<x-index-page overflow="clip" :subtitle="__('import.ical_subscription.subtitle')">
    <x-slot:actions>
        <x-icon-btn icon="add" tone="primary" size="sm"
                    data-entry-modal-trigger
                    :href="route('admin.imports.ical-subscriptions.create')"
                    show-label>{{ __('import.ical_subscription.create') }}</x-icon-btn>
    </x-slot:actions>

    <div role="alert" class="alert alert-info alert-soft">
        <x-icon name="info" />
        <div>
            <h3 class="font-semibold">{{ __('import.ical_subscription.help_title') }}</h3>
            <div class="text-sm">{{ __('import.ical_subscription.help_text', ['days' => $lookbackDays]) }}</div>
        </div>
    </div>

    <x-table scroll="flex" :pinRows="true">
        <x-slot:head>
            <tr>
                <th>{{ __('import.ical_subscription.field_name') }}</th>
                <th>{{ __('import.ical_subscription.field_entity') }}</th>
                <th>{{ __('import.ical_subscription.field_user') }}</th>
                <th>{{ __('import.ical_subscription.col_source') }}</th>
                <th>{{ __('import.ical_subscription.col_last_sync') }}</th>
                <th>{{ __('import.ical_subscription.col_result') }}</th>
                <th></th>
            </tr>
        </x-slot:head>
        @forelse ($subscriptions as $subscription)
            @php
                /** @var \App\Models\Time\IcalSubscription $subscription */
                $result = (array) ($subscription->last_result ?? []);
            @endphp
            <tr>
                <td class="font-medium">{{ $subscription->name }}</td>
                <td>{{ $subscription->import_entity->label() }}</td>
                <td>{{ $subscription->user?->name ?? '—' }}</td>
                @if ($subscription->calendar_source !== null)
                    <td class="text-xs">{{ $calendarLabels[$subscription->calendar_source] ?? __('import.ical_subscription.source_missing') }}</td>
                @else
                    <td class="font-mono text-xs">{{ $subscription->sourceHost() }}</td>
                @endif
                <td class="text-sm">
                    @if (! $subscription->is_active)
                        <x-status-badge size="xs" tone="ghost">{{ __('import.ical_subscription.status_inactive') }}</x-status-badge>
                    @elseif ($subscription->last_status !== null)
                        <x-status-badge size="xs" :tone="$subscription->last_status->tone()" :title="$subscription->last_status === \App\Enums\Import\IcalSyncStatus::Failed ? $subscription->last_error : null">{{ $subscription->last_status->label() }}</x-status-badge>
                    @endif
                    <span class="text-xs text-muted">{{ $subscription->last_synced_at !== null ? \App\Support\CarbonFmt::fdatetime($subscription->last_synced_at) : __('import.ical_subscription.never') }}</span>
                </td>
                <td class="text-xs tabular-nums">
                    @if ($result !== [])
                        {{ __('import.ical_subscription.result_line', ['created' => $result['created'] ?? 0, 'updated' => $result['updated'] ?? 0, 'staged' => $result['staged'] ?? 0, 'removed' => $result['removed'] ?? 0]) }}
                    @else
                        —
                    @endif
                </td>
                <td class="text-right whitespace-nowrap">
                    <div class="flex justify-end gap-1">
                        <x-action-form :action="route('admin.imports.ical-subscriptions.sync', $subscription)">
                            <x-icon-btn icon="sync" type="submit" :label="__('import.ical_subscription.sync_now')" />
                        </x-action-form>
                        <x-icon-btn icon="edit"
                                    data-entry-modal-trigger
                                    :href="route('admin.imports.ical-subscriptions.edit', $subscription)"
                                    :label="__('import.ical_subscription.edit')" />
                    </div>
                </td>
            </tr>
        @empty
            <x-table.empty icon="event_repeat" :colspan="7" :title="__('import.ical_subscription.empty_title')" compact />
        @endforelse
    </x-table>

    <x-pagination :paginator="$subscriptions" standing />
</x-index-page>
@endsection
