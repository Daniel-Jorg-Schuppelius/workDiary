{{--
  Created on   : Fri Sep 04 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _assign_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Inbox-Zuordnung (Feature 152, MVP-759): eine Firma laut Anbieter wird
  Kunde, Fremdkunde eines Partners (neu angelegt), vorhandener Fremdkunde
  oder eigener Bestand — für alle ihre Abos ohne Halter, und gemerkt für
  den nächsten Import.
--}}
<x-modal
    :title="__('resale.inbox.assign_title', ['company' => $company])"
    icon="person_add"
    tone="primary"
    size="md"
    :action="route('finance.resale.inbox.store')"
    method="POST"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('resale.inbox.assign_submit')"
>
    <input type="hidden" name="company" value="{{ $company }}">
    <div class="text-sm text-base-content/70">{{ trans_choice('resale.inbox.assign_hint', $count, ['count' => $count]) }}</div>
    @if (($mentioned ?? []) !== [])
        <div class="text-xs" title="{{ __('resale.inbox.mentioned_hint') }}">
            <span class="font-medium">{{ __('resale.inbox.mentioned_title') }}:</span>
            @foreach ($mentioned as $mention)
                <span class="text-info">{{ trans_choice('resale.inbox.mentioned', $mention['vouchers'], ['customer' => $mention['customer']->name, 'count' => $mention['vouchers']]) }}</span>@if (! $loop->last) · @endif
            @endforeach
        </div>
    @endif

    {{-- Vorbelegung aus dem Rechnungstext-Vorschlag des Posteingangs (Partner). --}}
    @include('finance.resale._holder_picker', [
        'mode' => (string) old('mode', $prefillMode ?? 'customer'),
        'modes' => ['customer', 'partner', 'foreign', 'own'],
        'modeLabel' => __('resale.inbox.mode'),
        'modeHint' => __('resale.inbox.mode_hint'),
        'customerDefault' => $prefillCustomer ?? '',
    ])
</x-modal>
