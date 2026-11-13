{{--
  Created on   : Thu Nov 12 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _cockpit_panel.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Aufnahmeakte am Cockpit von Lead bzw. Kunde (Feature 200). Variablen: $lead (oder null), $customer (oder null), $intakes, $enabled (Pakete), $canOpen, $canEndRelationship. --}}
<x-card as="section" id="lead-intake" :title="__('lead_intake.title')" icon="assignment_ind" :count="$intakes->count() ?: null" padding="p-0">
    @if ($customer !== null)
        <div class="flex flex-wrap items-center justify-between gap-2 border-b border-base-300 px-4 py-3 text-sm">
            <span>
                {{ __('lead_intake.field.relationship_ended_on') }}:
                <strong>{{ $customer->relationship_ended_on?->fdate() ?? __('lead_intake.relationship.ongoing') }}</strong>
            </span>
            @if ($canEndRelationship)
                <x-icon-btn icon="event_busy" tone="ghost" size="xs" data-entry-modal-trigger
                            :href="route('leads.intakes.relationship.edit', $customer) . '?dialog=1'" show-label>{{ __('lead_intake.relationship.title') }}</x-icon-btn>
            @endif
        </div>
    @endif

    @if ($intakes->isEmpty())
        <div class="space-y-2 px-4 py-3 text-sm">
            <x-empty-state compact icon="assignment_ind" :title="__('lead_intake.empty.title')"
                           :message="__('lead_intake.cockpit.none', ['packages' => implode(', ', array_map(static fn ($package): string => $package->label(), $enabled))])" />
            @if ($canOpen && $lead !== null)
                <x-action-form :action="route('leads.intakes.store', $lead)">
                    <x-icon-btn icon="add" tone="primary" size="sm" type="submit" show-label>{{ __('lead_intake.action.open') }}</x-icon-btn>
                </x-action-form>
            @endif
        </div>
    @else
        <ul class="divide-y divide-base-300">
            @foreach ($intakes as $intake)
                <li class="flex flex-wrap items-center justify-between gap-2 px-4 py-3 text-sm" data-lead-intake="{{ $intake->sqid }}">
                    <div class="min-w-0">
                        @if ($intake->lead !== null)
                            <a class="link link-hover font-medium" href="{{ route('leads.intakes.show', [$intake->lead, $intake]) }}">
                                {{ __('lead_intake.revision', ['revision' => $intake->revision]) }} · {{ $intake->lead->displayName() }}
                            </a>
                        @endif
                        <div class="text-xs text-muted">
                            {{ implode(', ', array_map(static fn ($p): string => $p->label, $intake->packageSnapshots())) ?: __('lead_intake.packages.none') }}
                            @if ($intake->refresh_due_on !== null)
                                · {{ __('lead_intake.cockpit.refresh_due', ['date' => $intake->refresh_due_on->fdate()]) }}
                            @endif
                            @if ($intake->retention_until !== null)
                                · {{ __('lead_intake.cockpit.retention', ['date' => $intake->retention_until->fdate()]) }}
                            @endif
                        </div>
                    </div>
                    <div class="flex items-center gap-2">
                        @if ($intake->decision !== null)
                            <x-status-badge size="xs" :tone="$intake->decision->tone()">{{ $intake->decision->label() }}</x-status-badge>
                        @endif
                        <x-status-badge size="xs" :tone="$intake->stage->tone()">{{ $intake->stage->label() }}</x-status-badge>
                    </div>
                </li>
            @endforeach
        </ul>
        @if ($lead !== null && $intakes->first()?->requiresDecision() && ! $intakes->first()?->isAccepted())
            <p class="border-t border-base-300 px-4 py-2 text-xs text-muted">{{ __('lead_intake.decision.blocks_conversion') }}</p>
        @endif
    @endif
</x-card>
