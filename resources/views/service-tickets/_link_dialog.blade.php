{{--
  Created on   : Sun Jul 12 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _link_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Modal (Feature 065, MVP-160): Ticket mit Ticket verknüpfen. Erwartet:
  $ticket, $targets (Collection der 100 jüngsten Tickets der Org).
--}}

<x-modal
    :title="__('Ticket verknüpfen')"
    :eyebrow="$ticket->ticket_no"
    icon="add_link"
    tone="primary"
    size="md"
    :action="route('helpdesk.tickets.links.store', $ticket)"
    method="POST"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('Verknüpfen')">

    <x-form-group :legend="__('Verknüpfung')" icon="link" tone="primary" cols="1">
        <x-select-field name="kind" :label="__('Art')" required>
            <option value="related" @selected(old('kind', 'related') === 'related')>{{ __('Verwandt') }}</option>
            <option value="duplicate" @selected(old('kind') === 'duplicate')>{{ __('Duplikat') }}</option>
            <option value="parent" @selected(old('kind') === 'parent')>{{ __('Übergeordnet') }}</option>
        </x-select-field>

        <x-select-field name="target" :label="__('Ziel-Ticket')" required>
            <option value="">{{ __('Ticket auswählen…') }}</option>
            @foreach ($targets as $target)
                <option value="{{ $target->sqid }}" @selected(old('target') === $target->sqid)>
                    {{ $target->ticket_no }} — {{ \Illuminate\Support\Str::limit($target->title, 60) }}
                </option>
            @endforeach
        </x-select-field>
    </x-form-group>
</x-modal>
