{{--
  Created on   : Sun Sep 27 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _proposal_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Investition vorschlagen (MVP-936) --}}
<x-modal
    :title="__('investment.proposal.nav')"
    :eyebrow="__('Investitionen')"
    icon="lightbulb"
    tone="primary"
    :action="route('investments.proposals.store')"
    method="POST"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('investment.proposal.submit')"
>
    <p class="mb-3 text-sm opacity-70">{{ __('investment.proposal.intro') }}</p>
    <x-form-group :legend="__('investment.proposal.nav')" icon="lightbulb" tone="primary" cols="2">
        @include('investments._proposal_fields')
    </x-form-group>
</x-modal>
