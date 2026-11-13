{{--
  Created on   : Thu Nov 12 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _handover_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Endgültige Übergabe ohne Austritt (Feature 202, MVP-1648): dieselben Bereiche wie
     beim Austritt, je Bereich eine Nachfolge. Variablen: $member, $handovers, $candidates,
     $origin ('delegations', wenn der Dialog auf der Seite „Vertretungen“ geöffnet wurde) --}}
<x-modal
    :title="__('delegation.handover.title', ['name' => $member->name])"
    :eyebrow="__('delegation.title')"
    icon="move_up"
    tone="primary"
    :action="route('delegations.handover.store', $member)"
    method="POST"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('delegation.handover.submit')"
    :hide-footer="$handovers === []">

    @if (($origin ?? null) !== null)
        <input type="hidden" name="origin" value="{{ $origin }}">
    @endif
    <div role="note" class="alert alert-info alert-soft text-sm">{{ __('delegation.handover.intro') }}</div>

    @if ($handovers === [])
        <x-empty-state icon="task_alt" :title="__('delegation.handover.empty')" compact />
    @else
        @include('org.members._handover_fields', [
            'handovers' => $handovers,
            'candidates' => $candidates,
            'legend' => __('delegation.handover.areas'),
            'hint' => __('delegation.handover.hint'),
        ])
        <p class="text-xs text-muted">
            {{ __('delegation.handover.single_records') }}
            <button type="button" class="link link-primary" data-help-trigger data-help-topic="delegations.manage">{{ __('delegation.handover.help') }}</button>
        </p>
    @endif
</x-modal>
