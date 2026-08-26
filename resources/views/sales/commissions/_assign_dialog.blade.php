{{--
  Created on   : Wed Aug 26 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _assign_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Manuelle Zuordnung Beleg → Vertriebsperson (Feature 146).
  Variablen: $invoice, $users, $suggestion (CommissionAssignment|null)
--}}
<x-modal
    :title="__('commission.action.assign')"
    :eyebrow="$invoice->number ?? $invoice->sqid"
    icon="person_add"
    tone="primary"
    size="md"
    :action="route('commissions.assign', $invoice)"
    method="POST"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('commission.action.save')">

    <x-form-group :legend="__('commission.field.user')" icon="badge" tone="primary" cols="1">
        <x-select-field name="user_id" :label="__('commission.field.user')" :hint="__('commission.hint.assign')">
            <option value="">—</option>
            @foreach ($users as $user)
                <option value="{{ $user->sqid }}" @selected((string) old('user_id', $invoice->salesUser?->sqid) === $user->sqid)>{{ $user->name }}</option>
            @endforeach
        </x-select-field>

        @if ($suggestion !== null)
            <p class="text-xs text-muted">
                {{ __('commission.hint.current_assignment', ['user' => $suggestion->user->name, 'source' => $suggestion->source->label()]) }}
            </p>
        @else
            <p class="text-xs text-muted">{{ __('commission.hint.no_assignment') }}</p>
        @endif
    </x-form-group>
</x-modal>
