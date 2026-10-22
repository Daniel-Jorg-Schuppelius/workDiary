{{--
  Created on   : Wed Oct 21 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _link_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Knoten mit vorhandenem Kunden, Projekt oder Auftrag verknüpfen. Der
  Optionswert trägt den Typ („customer:…“), Aufträge nur die zuletzt
  angelegten, die die Person sehen darf.
--}}
<x-modal
    :title="__('ideas.link.title')"
    :eyebrow="$node->title"
    icon="link"
    tone="primary"
    size="md"
    :action="route('ideas.nodes.link', [$map, $node])"
    method="POST"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('ideas.link.action')">
    <x-select-field name="target" id="idea-link-target" :label="__('ideas.link.target')" required>
        <optgroup label="{{ __('ideas.link.customers') }}">
            @foreach ($customers as $customer)
                <option value="customer:{{ $customer->sqid }}">{{ $customer->name }}</option>
            @endforeach
        </optgroup>
        <optgroup label="{{ __('ideas.link.projects') }}">
            @foreach ($projects as $project)
                <option value="project:{{ $project->sqid }}">{{ $project->name }}</option>
            @endforeach
        </optgroup>
        <optgroup label="{{ __('ideas.link.orders') }}">
            @foreach ($orders as $order)
                <option value="diary:{{ $order->sqid }}">{{ $order->title }}{{ $order->start_at ? ' · ' . $order->start_at->fdate() : '' }}</option>
            @endforeach
        </optgroup>
    </x-select-field>
</x-modal>
