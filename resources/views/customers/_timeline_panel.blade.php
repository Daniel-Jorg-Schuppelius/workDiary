{{--
  Created on   : Wed Jun 10 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _timeline_panel.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Kunden-Timeline light (Feature 023): letzte Ereignisse über die Aufträge
  des Kunden hinweg (Aufträge angelegt/abgeschlossen, Kommunikation, Dokumente).
  Erwartet: $customer
--}}
@php
    /** @var \App\Models\User $timelineViewer */
    $timelineViewer = \Illuminate\Support\Facades\Auth::user();
    $customerTimeline = app(\App\Services\Timeline\DiaryEntryTimelineService::class)
        ->forCustomer($customer, $timelineViewer, 15);
@endphp

<x-card as="section" id="customer-timeline" :title="__('timeline.title.customer_section')" icon="history">
    @include('timeline._items', ['items' => $customerTimeline['items']])
</x-card>
