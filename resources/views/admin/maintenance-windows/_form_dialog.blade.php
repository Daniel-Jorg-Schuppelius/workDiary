{{--
  Created on   : Thu Jul 09 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _form_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Dialog: Wartungsfenster planen (MVP-055) --}}
<x-modal
    :title="__('maintenance.window.action.plan')"
    :eyebrow="__('maintenance.window.title')"
    icon="engineering"
    tone="warning"
    :action="route('admin.maintenance-windows.store')"
    method="POST"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('maintenance.window.action.save')"
>
    <x-form-group :legend="__('maintenance.window.field.window')" icon="schedule" tone="warning" cols="2">
        <div class="fieldset">
            <span class="fieldset-label">{{ __('maintenance.window.field.scope') }}</span>
            <select name="scope" class="select select-bordered w-full">
                <option value="system">{{ __('maintenance.window.scope.system') }}</option>
                <option value="organization">{{ __('maintenance.window.scope.organization') }}</option>
            </select>
        </div>
        <div class="fieldset">
            <span class="fieldset-label">{{ __('maintenance.window.field.announce_from') }}</span>
            <input type="datetime-local" name="announce_from" class="input input-bordered w-full" value="{{ old('announce_from') }}">
            @error('announce_from')<p class="mt-1 text-sm text-error">{{ $message }}</p>@enderror
        </div>
        {{-- Von-Bis gekoppelt (I6): Ende nie vor Beginn; Feldnamen unverändert. --}}
        <x-date-range layout="split" grid-class="contents" form-control size="" type="datetime-local"
                      from-name="starts_at" to-name="ends_at"
                      from-id="starts_at" to-id="ends_at" required
                      :from-label="__('maintenance.window.field.starts_at')"
                      :to-label="__('maintenance.window.field.ends_at')"
                      :from="old('starts_at')" :to="old('ends_at')"
                      :from-error="$errors->first('starts_at') ?: null"
                      :to-error="$errors->first('ends_at') ?: null" />
        <div class="fieldset md:col-span-2">
            <span class="fieldset-label">{{ __('maintenance.window.field.message') }}</span>
            <input aria-label="{{ __('maintenance.window.hint.message') }}" type="text" name="message" maxlength="300" class="input input-bordered w-full" value="{{ old('message') }}"
                   placeholder="{{ __('maintenance.window.hint.message') }}">
        </div>
        <label class="label cursor-pointer justify-start gap-3">
            <input type="hidden" name="read_only" value="0">
            <input type="checkbox" name="read_only" value="1" class="toggle toggle-warning" @checked(old('read_only'))>
            <span class="label-text text-sm">{{ __('maintenance.window.mode.read_only_toggle') }}</span>
        </label>
        <label class="label cursor-pointer justify-start gap-3">
            <input type="hidden" name="block_ingest" value="0">
            <input type="checkbox" name="block_ingest" value="1" class="toggle" @checked(old('block_ingest'))>
            <span class="label-text text-sm">{{ __('maintenance.window.mode.block_ingest_toggle') }}</span>
        </label>
    </x-form-group>
</x-modal>
