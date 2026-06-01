{{-- Erwartet: $supplier, $isDialog --}}
@php
    $isDialog = $isDialog ?? false;
    $action = $supplier ? route('suppliers.update', $supplier) : route('suppliers.store');
    $dialogUrl = ($supplier ? route('suppliers.edit', $supplier) : route('suppliers.create')) . '?dialog=1';
@endphp

<x-modal
    :title="$supplier ? __('Lieferant bearbeiten') : __('Neuer Lieferant')"
    :eyebrow="__('Lieferant')"
    icon="local_shipping"
    tone="primary"
    :action="$action"
    :method="$supplier ? 'PUT' : 'POST'"
    :form-data="['data-entry-form' => '']"
    :submit-label="$supplier ? __('Speichern') : __('Anlegen')">
    @if ($isDialog)
        <input type="hidden" name="_dialog_url" value="{{ $dialogUrl }}">
    @endif

    <x-form-group :legend="__('Stammdaten')" icon="local_shipping" tone="primary" cols="2">
            <div class="fieldset">
                <label class="fieldset-label">{{ __('Name') }} *</label>
                <input name="name" type="text" required maxlength="200"
                       class="input input-bordered w-full"
                       value="{{ old('name', $supplier?->name) }}">
                @error('name')<p class="text-error text-sm">{{ $message }}</p>@enderror
            </div>
            <div class="fieldset">
                <label class="fieldset-label">{{ __('Lieferantennummer (intern)') }}</label>
                <input name="number" type="text" maxlength="64"
                       class="input input-bordered w-full"
                       value="{{ old('number', $supplier?->number) }}">
                @error('number')<p class="text-error text-sm">{{ $message }}</p>@enderror
            </div>

            <div class="fieldset">
                <label class="fieldset-label">{{ __('Firma') }}</label>
                <input name="company" type="text" maxlength="200"
                       class="input input-bordered w-full"
                       value="{{ old('company', $supplier?->company) }}">
            </div>
            <div class="fieldset">
                <label class="fieldset-label">{{ __('USt-IdNr.') }}</label>
                <input name="vat_id" type="text" maxlength="64"
                       class="input input-bordered w-full"
                       value="{{ old('vat_id', $supplier?->vat_id) }}">
            </div>

            <div class="fieldset">
                <label class="fieldset-label">{{ __('Lieferantennr. (Lexoffice)') }}</label>
                <input name="vendor_number" type="text" maxlength="64"
                       class="input input-bordered w-full"
                       value="{{ old('vendor_number', $supplier?->vendor_number) }}">
            </div>
            <div class="fieldset">
                <label class="fieldset-label cursor-pointer gap-3">
                    <input type="hidden" name="active" value="0">
                    <input type="checkbox" name="active" value="1" class="toggle toggle-primary"
                           @checked(old('active', $supplier?->active ?? true))>
                    <span>{{ __('Aktiv') }}</span>
                </label>
            </div>
        </x-form-group>

        <x-form-group :legend="__('Kontakt')" icon="call" tone="info" cols="2">
            <div class="fieldset">
                <label class="fieldset-label">{{ __('Ansprechpartner') }}</label>
                <input name="contact_name" type="text" maxlength="200"
                       class="input input-bordered w-full"
                       value="{{ old('contact_name', $supplier?->contact_name) }}">
            </div>
            <div class="fieldset">
                <label class="fieldset-label">{{ __('E-Mail') }}</label>
                <input name="email" type="email" maxlength="255"
                       class="input input-bordered w-full"
                       value="{{ old('email', $supplier?->email) }}">
                @error('email')<p class="text-error text-sm">{{ $message }}</p>@enderror
            </div>

            <div class="fieldset">
                <label class="fieldset-label">{{ __('Telefon') }}</label>
                <input name="phone" type="text" maxlength="64"
                       class="input input-bordered w-full"
                       value="{{ old('phone', $supplier?->phone) }}">
            </div>
            <div class="fieldset">
                <label class="fieldset-label">{{ __('Mobil') }}</label>
                <input name="mobile" type="text" maxlength="64"
                       class="input input-bordered w-full"
                       value="{{ old('mobile', $supplier?->mobile) }}">
            </div>
        </x-form-group>

        <x-form-group :legend="__('Adresse')" icon="home" tone="ghost" cols="2">
            <div class="fieldset md:col-span-2">
                <label class="fieldset-label">{{ __('Adresse (Freitext, optional)') }}</label>
                <textarea name="address" rows="2" maxlength="1000"
                          class="textarea textarea-bordered w-full">{{ old('address', $supplier?->address) }}</textarea>
                <p class="text-xs text-base-content/60 mt-1">{{ __('Wird nur genutzt, wenn die strukturierten Felder darunter leer sind.') }}</p>
            </div>

            <div class="fieldset md:col-span-2">
                <label class="fieldset-label">{{ __('Straße / Hausnr.') }}</label>
                <input name="address_street" type="text" maxlength="255"
                       class="input input-bordered w-full"
                       value="{{ old('address_street', $supplier?->address_street) }}">
            </div>

            <div class="fieldset">
                <label class="fieldset-label">{{ __('PLZ') }}</label>
                <input name="address_zip" type="text" maxlength="32"
                       class="input input-bordered w-full"
                       value="{{ old('address_zip', $supplier?->address_zip) }}">
            </div>
            <div class="fieldset">
                <label class="fieldset-label">{{ __('Ort') }}</label>
                <input name="address_city" type="text" maxlength="128"
                       class="input input-bordered w-full"
                       value="{{ old('address_city', $supplier?->address_city) }}">
            </div>

            <div class="fieldset">
                <label class="fieldset-label">{{ __('Land (ISO 2)') }}</label>
                <input name="country" type="text" maxlength="2"
                       class="input input-bordered w-full uppercase"
                       value="{{ old('country', $supplier?->country) }}">
            </div>
            <div class="fieldset">
                <label class="fieldset-label">{{ __('Homepage') }}</label>
                <input name="homepage" type="url" maxlength="255"
                       class="input input-bordered w-full"
                       value="{{ old('homepage', $supplier?->homepage) }}">
                @error('homepage')<p class="text-error text-sm">{{ $message }}</p>@enderror
            </div>
        </x-form-group>

        <x-form-group :legend="__('Darstellung')" icon="palette" tone="warning" cols="2">
            <div class="fieldset">
                <label class="fieldset-label">{{ __('Währung') }}</label>
                <input name="currency" type="text" maxlength="3" required
                       class="input input-bordered w-full uppercase"
                       value="{{ old('currency', $supplier?->currency ?? 'EUR') }}">
            </div>
            <div class="fieldset">
                <label class="fieldset-label">{{ __('Zeitzone') }}</label>
                <input name="timezone" type="text" maxlength="64"
                       class="input input-bordered w-full"
                       value="{{ old('timezone', $supplier?->timezone) }}"
                       placeholder="Europe/Berlin">
                @error('timezone')<p class="text-error text-sm">{{ $message }}</p>@enderror
            </div>

            <div class="fieldset">
                <label class="fieldset-label">{{ __('Farbe') }}</label>
                <input name="color" type="text" maxlength="16"
                       class="input input-bordered w-full"
                       value="{{ old('color', $supplier?->color) }}"
                       placeholder="#3b82f6">
            </div>
        </x-form-group>

        <x-form-group :legend="__('Bankverbindung')" icon="account_balance" tone="ghost" cols="2">
            <div class="fieldset md:col-span-2">
                <label class="fieldset-label">{{ __('Kontoinhaber') }}</label>
                <input name="bank_account_holder" type="text" maxlength="200"
                       class="input input-bordered w-full"
                       value="{{ old('bank_account_holder', $supplier?->bank_account_holder) }}">
            </div>
            <div class="fieldset">
                <label class="fieldset-label">{{ __('IBAN') }}</label>
                <input name="bank_iban" type="text" maxlength="64"
                       class="input input-bordered w-full uppercase"
                       value="{{ old('bank_iban', $supplier?->bank_iban) }}"
                       placeholder="DE00 0000 0000 0000 0000 00">
                @error('bank_iban')<p class="text-error text-sm">{{ $message }}</p>@enderror
            </div>
            <div class="fieldset">
                <label class="fieldset-label">{{ __('BIC') }}</label>
                <input name="bank_bic" type="text" maxlength="32"
                       class="input input-bordered w-full uppercase"
                       value="{{ old('bank_bic', $supplier?->bank_bic) }}">
                @error('bank_bic')<p class="text-error text-sm">{{ $message }}</p>@enderror
            </div>
            <div class="fieldset md:col-span-2">
                <label class="fieldset-label">{{ __('Bank') }}</label>
                <input name="bank_name" type="text" maxlength="200"
                       class="input input-bordered w-full"
                       value="{{ old('bank_name', $supplier?->bank_name) }}">
            </div>
        </x-form-group>

        <x-form-group :legend="__('Notizen')" icon="description" tone="ghost">
            <div class="fieldset">
                <label class="fieldset-label">{{ __('Notiz (intern)') }}</label>
                <textarea name="comment" rows="2" maxlength="5000"
                          class="textarea textarea-bordered w-full">{{ old('comment', $supplier?->comment) }}</textarea>
            </div>
        </x-form-group>

        @php
            $contactPersons = old('contact_persons', $supplier?->contact_persons ?? []);
            if (empty($contactPersons)) {
                $contactPersons = [['name' => '', 'email' => '', 'phone' => '', 'primary' => true]];
            }
        @endphp

        <div class="rounded-box border border-base-300 p-3" data-contact-persons>
            <div class="mb-2 flex items-center justify-between">
                <h3 class="font-medium text-sm">{{ __('Ansprechpartner') }}</h3>
                <x-icon-btn icon="person_add" type="button" data-contact-add show-label>{{ __('Person') }}</x-icon-btn>
            </div>
            <div class="space-y-2" data-contact-rows>
                @foreach ($contactPersons as $i => $cp)
                    <div class="grid grid-cols-1 gap-2 sm:grid-cols-12 items-center" data-contact-row>
                        <input type="text" name="contact_persons[{{ $i }}][name]" value="{{ $cp['name'] ?? '' }}"
                               placeholder="{{ __('Name') }}" maxlength="200"
                               class="input input-bordered sm:col-span-3">
                        <input type="email" name="contact_persons[{{ $i }}][email]" value="{{ $cp['email'] ?? '' }}"
                               placeholder="{{ __('E-Mail') }}" maxlength="255"
                               class="input input-bordered sm:col-span-4">
                        <input type="text" name="contact_persons[{{ $i }}][phone]" value="{{ $cp['phone'] ?? '' }}"
                               placeholder="{{ __('Telefon') }}" maxlength="64"
                               class="input input-bordered sm:col-span-3">
                        <label class="label cursor-pointer gap-1 text-xs sm:col-span-1">
                            <input type="hidden" name="contact_persons[{{ $i }}][primary]" value="0">
                            <input type="checkbox" name="contact_persons[{{ $i }}][primary]" value="1"
                                   class="checkbox checkbox-xs"
                                   @checked($cp['primary'] ?? false)>
                            <span>{{ __('Primär') }}</span>
                        </label>
                        <x-icon-btn icon="close" type="button" data-contact-remove class="sm:col-span-1" :label="__('Entfernen')" />
                    </div>
                @endforeach
            </div>
            @error('contact_persons')<p class="text-error text-sm mt-1">{{ $message }}</p>@enderror
        </div>

        @php
            $allTags = $allTags ?? collect();
            $selectedTagIds = old('tag_ids', $supplier?->tags?->map(fn ($t) => $t->sqid)->all() ?? []);
        @endphp
        <div>
            <label class="label"><span class="label-text">{{ __('Tags') }}</span></label>
            <x-tag-picker :tags="$allTags" :selected="$selectedTagIds" />
        </div>
</x-modal>

<script>
(function () {
    const root = document.querySelector('[data-contact-persons]');
    if (!root) return;
    const rows = root.querySelector('[data-contact-rows]');
    const addBtn = root.querySelector('[data-contact-add]');

    const renumber = () => {
        rows.querySelectorAll('[data-contact-row]').forEach((row, idx) => {
            row.querySelectorAll('input[name]').forEach(inp => {
                inp.name = inp.name.replace(/contact_persons\[\d+\]/, 'contact_persons[' + idx + ']');
            });
        });
    };

    addBtn?.addEventListener('click', () => {
        const first = rows.querySelector('[data-contact-row]');
        if (!first) return;
        const clone = first.cloneNode(true);
        clone.querySelectorAll('input').forEach(inp => {
            if (inp.type === 'checkbox') { inp.checked = false; }
            else if (inp.type !== 'hidden') { inp.value = ''; }
        });
        rows.appendChild(clone);
        renumber();
    });

    rows?.addEventListener('click', (e) => {
        const target = e.target instanceof Element ? e.target : null;
        if (target && target.matches('[data-contact-remove]')) {
            const allRows = rows.querySelectorAll('[data-contact-row]');
            if (allRows.length > 1) {
                target.closest('[data-contact-row]')?.remove();
                renumber();
            } else {
                target.closest('[data-contact-row]')?.querySelectorAll('input').forEach(inp => {
                    if (inp.type === 'checkbox') inp.checked = false;
                    else if (inp.type !== 'hidden') inp.value = '';
                });
            }
        }
    });
})();
</script>
