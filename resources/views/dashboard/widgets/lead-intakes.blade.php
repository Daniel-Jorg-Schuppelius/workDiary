{{--
  Created on   : Thu Nov 12 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : lead-intakes.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Kachel „Aufnahmen“ (Feature 200) — Daten: LeadIntakesWidget ($stages, $counts, $total).
--}}
<x-card :title="__('lead_intake.widget.title')" icon="assignment_ind" :count="$total ?: null">
    <x-slot:actions>
        <x-button href="{{ route('leads.intakes.index') }}" tone="ghost" size="xs">{{ __('lead_intake.widget.open_list') }}</x-button>
    </x-slot:actions>

    @if ($total === 0)
        <x-empty-state compact icon="assignment_ind" :title="__('lead_intake.empty.title')" :message="__('lead_intake.widget.empty')" />
    @else
        <ul class="divide-y divide-base-300 text-sm">
            @foreach ($stages as $stage)
                <li class="flex items-center justify-between gap-3 py-2">
                    <a class="link link-hover" href="{{ route('leads.intakes.index', ['stage' => $stage->value]) }}">{{ $stage->label() }}</a>
                    <x-status-badge size="sm" :tone="($counts[$stage->value] ?? 0) > 0 ? $stage->tone() : 'ghost'">{{ (int) ($counts[$stage->value] ?? 0) }}</x-status-badge>
                </li>
            @endforeach
        </ul>
    @endif
</x-card>
