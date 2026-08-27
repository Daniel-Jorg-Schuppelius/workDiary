{{--
  Created on   : Thu Aug 27 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : recent-emergencies.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Kachel „Nächste Notdienste" — Daten: RecentEmergenciesWidget.
--}}
<x-card :title="__('Nächste Notdienste')" icon="emergency">
    @if ($emergencies->isEmpty())
        <x-empty-state compact icon="crisis_alert"
                       :title="__('Keine geplanten Notdienste')" :message="__('Keine geplanten Notdienste.')" />
    @else
        <ul class="space-y-2 text-sm">
            @foreach ($emergencies as $em)
                <li class="flex flex-wrap items-center justify-between gap-2 rounded-box border border-base-300 bg-base-200 px-3 py-2">
                    <span class="inline-flex items-center gap-1"><x-icon name="priority_high" /> {{ $em->start_at->format('d.m. H:i') }} – {{ $em->end_at->format('d.m. H:i') }}</span>
                    @if ($em->reason)<span class="text-muted">{{ \CommonToolkit\Helper\Data\StringHelper::truncate($em->reason, 50) }}</span>@endif
                </li>
            @endforeach
        </ul>
    @endif
</x-card>
