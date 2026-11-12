{{--
  Created on   : Wed Nov 11 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _token_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Dialog: Überwachungszugang zum Diagnose-JSON anlegen (MVP-1618) --}}
<x-modal
    :title="__('diagnostics.token.create')"
    icon="key"
    tone="primary"
    :action="route('admin.diagnostics.tokens.store')"
    method="POST"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('diagnostics.token.create')"
>
    <div class="fieldset">
        <label class="fieldset-label" for="monitoring-token-name">{{ __('diagnostics.token.name') }}</label>
        <input id="monitoring-token-name" type="text" name="name" required maxlength="120"
               value="{{ old('name') }}" class="input input-bordered w-full">
        <p class="text-xs text-muted">{{ __('diagnostics.token.name_help') }}</p>
        @error('name')<p class="mt-1 text-sm text-error">{{ $message }}</p>@enderror
    </div>
</x-modal>
