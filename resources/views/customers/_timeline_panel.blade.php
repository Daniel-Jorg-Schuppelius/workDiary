{{--
  Created on   : Wed Jun 10 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _timeline_panel.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Vollwertige Kunden-Timeline (MVP-340, Feature 023): alle kundenbezogenen
  Quellen (Aufträge, Protokolle, Kommunikation, Dokumente, Rechnungen,
  Angebote, Versand) mit Typ-Filter und Nachladen — Muster der
  Auftrags-Timeline (diary/_timeline_panel).
  Erwartet: $customer, $timelineItems, $timelineHasMore, $timelineType, $timelineLimit
--}}
<x-card as="section" id="customer-timeline" :title="__('timeline.title.customer_section')" icon="history">
    {{-- Filter-Chips nach Ereignistyp (serverseitig per Query-Param) --}}
    <nav class="mb-4 flex flex-wrap gap-1.5" aria-label="{{ __('timeline.filter.label') }}">
        <a href="{{ route('customers.show', $customer) }}#customer-timeline"
           @class(['badge badge-sm', 'badge-primary' => $timelineType === '', 'badge-ghost' => $timelineType !== ''])>
            {{ __('timeline.filter.all') }}
        </a>
        @foreach (\App\Services\Timeline\DiaryEntryTimelineService::CUSTOMER_TYPES as $typeKey)
            <a href="{{ route('customers.show', ['customer' => $customer, 'timeline_type' => $typeKey]) }}#customer-timeline"
               @class(['badge badge-sm', 'badge-primary' => $timelineType === $typeKey, 'badge-ghost' => $timelineType !== $typeKey])>
                {{ __('timeline.filter.' . $typeKey) }}
            </a>
        @endforeach
    </nav>

    @include('timeline._items', ['items' => $timelineItems])

    @if ($timelineHasMore)
        <div class="mt-4 text-center">
            <x-icon-btn icon="expand_more" tone="outline" size="sm" show-label
                        :href="route('customers.show', array_filter(['customer' => $customer, 'timeline_type' => $timelineType ?: null, 'timeline_limit' => $timelineLimit + 50])) . '#customer-timeline'">
                {{ __('timeline.action.load_more') }}
            </x-icon-btn>
        </div>
    @endif
</x-card>
