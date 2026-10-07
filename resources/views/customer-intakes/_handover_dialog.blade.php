{{--
  Created on   : Wed Oct 07 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _handover_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Dialog: Übernahme in die Fachakte (MVP-1075–1077). Erwartet: $intake, $blockers, $needsScopeNote, $formView, $formData --}}
<x-modal
    :title="__('customer_intake.action.handover')"
    :eyebrow="$intake->number"
    icon="forward"
    :action="$blockers === [] ? route('customer-intakes.handover', $intake) : null"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('customer_intake.action.handover_submit')"
>
    @if ($blockers !== [])
        <div role="alert" class="alert alert-warning text-sm">
            <div>
                <p class="font-semibold">{{ __('customer_intake.handover.not_yet') }}</p>
                <ul class="list-inside list-disc">
                    @foreach ($blockers as $blocker)
                        <li>{{ $blocker }}</li>
                    @endforeach
                </ul>
            </div>
        </div>
    @else
        <p class="text-sm text-muted">{{ __('customer_intake.handover.intro', ['quote' => $intake->quote?->number]) }}</p>
        @if ($formView !== null)
            @include($formView, $formData + ['intake' => $intake])
        @endif
        @if ($needsScopeNote)
            <x-form-group :legend="__('customer_intake.handover.scope_legend')" icon="rule" tone="warning" cols="1">
                <x-textarea-field name="scope_note" :label="__('customer_intake.field.scope_note')" rows="3" maxlength="2000" required :value="old('scope_note')"
                                  :hint="__('customer_intake.handover.scope_hint')" />
            </x-form-group>
        @endif
    @endif

    <x-validation-errors />
</x-modal>
