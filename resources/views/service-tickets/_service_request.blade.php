{{--
  Created on   : Tue Nov 10 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _service_request.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Katalogbestellung am Ticket (MVP-1549): eingefrorener Katalogstand, Antworten
  des Bestellformulars und das Erfüllungsobjekt. Erwartet: $ticket (serviceRequest geladen).
--}}
@php
    /** @var \App\Models\ServiceTicket\ServiceTicket $ticket */
    $serviceRequest = $ticket->serviceRequest;
@endphp
@if ($serviceRequest !== null)
    @php
        $catalog = (array) ($serviceRequest->catalog_snapshot ?? []);
        $form = $serviceRequest->formDocument();
        $fulfilled = $serviceRequest->fulfilled;
        $fulfilledUrl = \App\Support\EntityUrl::byType($serviceRequest->fulfilled_type, $serviceRequest->fulfilled_id);
        $fulfilledName = $fulfilled?->getAttribute('title') ?? $fulfilled?->getAttribute('name');
    @endphp
    <x-card :title="__('helpdesk.request.title')" icon="shopping_cart" data-ticket-service-request>
        <x-detail-grid layout="cells">
            <x-detail-grid.row :label="__('helpdesk.request.item')">
                {{ $catalog['name'] ?? '—' }}
                @if (isset($catalog['version']))
                    <span class="text-xs text-muted">· {{ __('helpdesk.request.version', ['version' => $catalog['version']]) }}</span>
                @endif
            </x-detail-grid.row>
            <x-detail-grid.row :label="__('Status')">
                <x-status-badge size="sm" outline>{{ $serviceRequest->status->label() }}</x-status-badge>
            </x-detail-grid.row>
            <x-detail-grid.row :label="__('helpdesk.request.fulfilled')" data-ticket-fulfilment>
                @if ($serviceRequest->fulfilled_id === null)
                    {{ __('helpdesk.request.notFulfilled') }}
                @else
                    {{ \App\Support\EntityType::label($serviceRequest->fulfilled_type) }}
                    @if ($fulfilledUrl !== null)
                        · <a class="link" href="{{ $fulfilledUrl }}">{{ $fulfilledName ?: __('helpdesk.request.open') }}</a>
                    @elseif ($fulfilledName)
                        · {{ $fulfilledName }}
                    @endif
                @endif
            </x-detail-grid.row>
        </x-detail-grid>

        @if ($form !== null)
            <div class="divider my-2"></div>
            <div class="mb-1 text-xs uppercase text-muted">{{ __('helpdesk.request.answers') }}</div>
            <div data-ticket-request-answers>
                <x-detail-grid layout="cells">
                    @foreach ($form->schema->visibleFor($form->values->toArray()) as $field)
                        <x-detail-grid.row :label="$field->label"><x-field-display :field="$field" :values="$form->values" /></x-detail-grid.row>
                    @endforeach
                </x-detail-grid>
            </div>
        @endif
    </x-card>
@endif
