{{--
  Created on   : Thu Nov 12 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _cockpit_panel.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Kundenfristen am Kunden-Cockpit (Feature 197, MVP-1633). Variablen: $customer, $profiles (Schlüssel → rule_set, profile, derived), $deadlines, $count, $overview, $organization, $warnDays, $today, $canManage. --}}
<x-card as="section" id="customer-deadlines" :title="__('customer_deadline.title')" icon="event_upcoming" :count="$count" padding="p-0">
    @foreach ($profiles as $key => $entry)
        <div class="border-b border-base-300 px-4 py-3 text-sm" data-deadline-profile="{{ $key }}">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <span class="font-medium">{{ $entry['rule_set']->label() }}</span>
                @if ($canManage)
                    <x-icon-btn icon="edit_note" tone="ghost" size="xs" data-entry-modal-trigger
                                :href="route('customers.deadline-profile.edit', $customer) . '?dialog=1&rule_set=' . $key"
                                show-label>{{ __('customer_deadline.profile.title') }}</x-icon-btn>
                @endif
            </div>
            @if ($entry['profile'] !== null)
                <div class="text-xs text-muted">
                    {{ __('customer_deadline.profile.since', ['date' => $entry['profile']->valid_from->fdate()]) }}
                    · {{ $entry['profile']->responsible?->name ?? __('customer_deadline.filter.unassigned') }}
                </div>
                <x-detail-grid layout="split" cols="2" small-labels class="mt-1">
                    @foreach ($entry['derived'] as $label => $value)
                        <x-detail-grid.row :label="$label">{{ $value }}</x-detail-grid.row>
                    @endforeach
                    @if ($entry['profile']->traits !== null)
                        @foreach ($entry['profile']->traits->schema as $field)
                            @continue($entry['profile']->traits->values->isEmpty($field))
                            <x-detail-grid.row :label="$field->label"><x-field-display :field="$field" :values="$entry['profile']->traits->values" /></x-detail-grid.row>
                        @endforeach
                    @endif
                </x-detail-grid>
            @else
                <x-empty-state compact icon="event_note" :title="__('customer_deadline.profile.none')" />
            @endif
        </div>
    @endforeach

    @if ($deadlines->isNotEmpty())
        <ul class="divide-y divide-base-300">
            @foreach ($deadlines as $deadline)
                @php($light = $deadline->trafficLight($today, $warnDays[$deadline->rule_set] ?? \App\Services\Customer\Deadlines\CustomerDeadlineRuleSets::DEFAULT_WARN_DAYS))
                <li class="flex flex-wrap items-center justify-between gap-2 px-4 py-2 text-sm">
                    <div class="min-w-0">
                        <a class="link link-hover font-medium" data-entry-modal-trigger href="{{ route('customer-deadlines.edit', $deadline) }}?dialog=1">{{ $overview->kindLabel($organization, $deadline) }}</a>
                        <div class="truncate text-xs text-muted">{{ $deadline->period_key->label() }} · {{ $deadline->responsible?->name ?? '—' }}</div>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="text-xs tabular-nums text-muted">{{ $deadline->due_on->fdate() }}</span>
                        @if ($light !== null)
                            <x-status-badge size="xs" :tone="$light">{{ __('customer_deadline.light.' . $light) }}</x-status-badge>
                        @endif
                    </div>
                </li>
            @endforeach
        </ul>
    @endif

    <div class="flex flex-wrap items-center justify-between gap-2 border-t border-base-300 px-4 py-2 text-xs">
        <a class="link link-hover" href="{{ route('customer-deadlines.index', ['customer' => $customer->sqid, 'status' => 'all']) }}">{{ __('customer_deadline.cockpit.all') }}</a>
        @if ($canManage)
            <a class="link link-hover" data-entry-modal-trigger href="{{ route('customers.services.index', $customer) }}?dialog=1">{{ __('customer_deadline.service.title') }}</a>
        @endif
    </div>
</x-card>
