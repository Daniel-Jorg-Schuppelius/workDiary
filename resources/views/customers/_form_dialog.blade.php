{{-- Erwartet: $customer, $isDialog --}}
@php
    $isDialog = $isDialog ?? false;
    $action = $customer ? route('customers.update', $customer) : route('customers.store');
    $dialogUrl = ($customer ? route('customers.edit', $customer) : route('customers.create')) . '?dialog=1';
@endphp

<x-dialog
    :title="$customer ? __('Kunde bearbeiten') : __('Neuer Kunde')"
    :eyebrow="__('Kunde')"
    icon="◉"
    tone="primary">
    <form method="POST" action="{{ $action }}" class="space-y-4" data-entry-form>
        @csrf
        @if ($customer) @method('PUT') @endif
        @if ($isDialog)
            <input type="hidden" name="_dialog_url" value="{{ $dialogUrl }}">
        @endif

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div class="fieldset">
                <label class="fieldset-label">{{ __('Name') }} *</label>
                <input name="name" type="text" required maxlength="200"
                       class="input input-bordered w-full"
                       value="{{ old('name', $customer?->name) }}">
                @error('name')<p class="text-error text-sm">{{ $message }}</p>@enderror
            </div>
            <div class="fieldset">
                <label class="fieldset-label">{{ __('Kundennummer') }}</label>
                <input name="number" type="text" maxlength="64"
                       class="input input-bordered w-full"
                       value="{{ old('number', $customer?->number) }}">
                @error('number')<p class="text-error text-sm">{{ $message }}</p>@enderror
            </div>

            <div class="fieldset">
                <label class="fieldset-label">{{ __('Firma') }}</label>
                <input name="company" type="text" maxlength="200"
                       class="input input-bordered w-full"
                       value="{{ old('company', $customer?->company) }}">
            </div>
            <div class="fieldset">
                <label class="fieldset-label">{{ __('USt-IdNr.') }}</label>
                <input name="vat_id" type="text" maxlength="64"
                       class="input input-bordered w-full"
                       value="{{ old('vat_id', $customer?->vat_id) }}">
            </div>

            <div class="fieldset">
                <label class="fieldset-label">{{ __('Ansprechpartner') }}</label>
                <input name="contact_name" type="text" maxlength="200"
                       class="input input-bordered w-full"
                       value="{{ old('contact_name', $customer?->contact_name) }}">
            </div>
            <div class="fieldset">
                <label class="fieldset-label">{{ __('E-Mail') }}</label>
                <input name="email" type="email" maxlength="255"
                       class="input input-bordered w-full"
                       value="{{ old('email', $customer?->email) }}">
                @error('email')<p class="text-error text-sm">{{ $message }}</p>@enderror
            </div>

            <div class="fieldset">
                <label class="fieldset-label">{{ __('Telefon') }}</label>
                <input name="phone" type="text" maxlength="64"
                       class="input input-bordered w-full"
                       value="{{ old('phone', $customer?->phone) }}">
            </div>
            <div class="fieldset">
                <label class="fieldset-label">{{ __('Mobil') }}</label>
                <input name="mobile" type="text" maxlength="64"
                       class="input input-bordered w-full"
                       value="{{ old('mobile', $customer?->mobile) }}">
            </div>

            <div class="fieldset sm:col-span-2">
                <label class="fieldset-label">{{ __('Adresse (Freitext, optional)') }}</label>
                <textarea name="address" rows="2" maxlength="1000"
                          class="textarea textarea-bordered w-full">{{ old('address', $customer?->address) }}</textarea>
                <p class="text-xs text-base-content/60 mt-1">{{ __('Wird nur genutzt, wenn die strukturierten Felder darunter leer sind.') }}</p>
            </div>

            <div class="fieldset sm:col-span-2">
                <label class="fieldset-label">{{ __('Straße / Hausnr.') }}</label>
                <input name="address_street" type="text" maxlength="255"
                       class="input input-bordered w-full"
                       value="{{ old('address_street', $customer?->address_street) }}">
            </div>

            <div class="fieldset">
                <label class="fieldset-label">{{ __('PLZ') }}</label>
                <input name="address_zip" type="text" maxlength="32"
                       class="input input-bordered w-full"
                       value="{{ old('address_zip', $customer?->address_zip) }}">
            </div>
            <div class="fieldset">
                <label class="fieldset-label">{{ __('Ort') }}</label>
                <input name="address_city" type="text" maxlength="128"
                       class="input input-bordered w-full"
                       value="{{ old('address_city', $customer?->address_city) }}">
            </div>

            <div class="fieldset">
                <label class="fieldset-label">{{ __('Land (ISO 2)') }}</label>
                <input name="country" type="text" maxlength="2"
                       class="input input-bordered w-full uppercase"
                       value="{{ old('country', $customer?->country) }}">
            </div>
            <div class="fieldset">
                <label class="fieldset-label">{{ __('Homepage') }}</label>
                <input name="homepage" type="url" maxlength="255"
                       class="input input-bordered w-full"
                       value="{{ old('homepage', $customer?->homepage) }}">
                @error('homepage')<p class="text-error text-sm">{{ $message }}</p>@enderror
            </div>

            <div class="fieldset">
                <label class="fieldset-label">{{ __('Währung') }}</label>
                <input name="currency" type="text" maxlength="3" required
                       class="input input-bordered w-full uppercase"
                       value="{{ old('currency', $customer?->currency ?? 'EUR') }}">
            </div>
            <div class="fieldset">
                <label class="fieldset-label">{{ __('Zeitzone') }}</label>
                <input name="timezone" type="text" maxlength="64"
                       class="input input-bordered w-full"
                       value="{{ old('timezone', $customer?->timezone) }}"
                       placeholder="Europe/Berlin">
                @error('timezone')<p class="text-error text-sm">{{ $message }}</p>@enderror
            </div>

            <div class="fieldset">
                <label class="fieldset-label">{{ __('Stundensatz') }}</label>
                <input name="hourly_rate" type="number" step="0.01" min="0"
                       class="input input-bordered w-full"
                       value="{{ old('hourly_rate', $customer?->hourly_rate) }}">
            </div>
            <div class="fieldset">
                <label class="fieldset-label">{{ __('Interner Satz') }}</label>
                <input name="internal_rate" type="number" step="0.01" min="0"
                       class="input input-bordered w-full"
                       value="{{ old('internal_rate', $customer?->internal_rate) }}">
            </div>

            <div class="fieldset">
                <label class="fieldset-label">{{ __('Farbe') }}</label>
                <input name="color" type="color"
                       value="{{ old('color', $customer?->color ?? '#3b82f6') }}"
                       class="input input-bordered h-10 w-20 p-1">
            </div>
            <div class="fieldset">
                <label class="fieldset-label cursor-pointer gap-3">
                    <input type="hidden" name="billable" value="0">
                    <input type="checkbox" name="billable" value="1" class="toggle toggle-primary"
                           @checked(old('billable', $customer?->billable ?? true))>
                    <span>{{ __('Abrechenbar') }}</span>
                </label>
            </div>

            <div class="fieldset sm:col-span-2">
                <label class="fieldset-label">{{ __('Notiz (intern)') }}</label>
                <textarea name="comment" rows="2" maxlength="5000"
                          class="textarea textarea-bordered w-full">{{ old('comment', $customer?->comment) }}</textarea>
            </div>

            <div class="fieldset sm:col-span-2">
                <label class="fieldset-label">{{ __('Rechnungstext') }}</label>
                <textarea name="invoice_text" rows="2" maxlength="5000"
                          class="textarea textarea-bordered w-full">{{ old('invoice_text', $customer?->invoice_text) }}</textarea>
            </div>
        </div>

        @php
            $contactPersons = old('contact_persons', $customer?->contact_persons ?? []);
            // Mindestens eine leere Zeile als Eingabehilfe
            if (empty($contactPersons)) {
                $contactPersons = [['name' => '', 'email' => '', 'phone' => '', 'primary' => true]];
            }
        @endphp

        <div class="rounded-box border border-base-300 p-3" data-contact-persons>
            <div class="mb-2 flex items-center justify-between">
                <h3 class="font-medium text-sm">{{ __('Ansprechpartner') }}</h3>
                <button type="button" class="btn btn-xs btn-ghost" data-contact-add>+ {{ __('Person') }}</button>
            </div>
            <div class="space-y-2" data-contact-rows>
                @foreach ($contactPersons as $i => $cp)
                    <div class="grid grid-cols-1 gap-2 sm:grid-cols-12 items-center" data-contact-row>
                        <input type="text" name="contact_persons[{{ $i }}][name]" value="{{ $cp['name'] ?? '' }}"
                               placeholder="{{ __('Name') }}" maxlength="200"
                               class="input input-sm input-bordered sm:col-span-3">
                        <input type="email" name="contact_persons[{{ $i }}][email]" value="{{ $cp['email'] ?? '' }}"
                               placeholder="{{ __('E-Mail') }}" maxlength="255"
                               class="input input-sm input-bordered sm:col-span-4">
                        <input type="text" name="contact_persons[{{ $i }}][phone]" value="{{ $cp['phone'] ?? '' }}"
                               placeholder="{{ __('Telefon') }}" maxlength="64"
                               class="input input-sm input-bordered sm:col-span-3">
                        <label class="label cursor-pointer gap-1 text-xs sm:col-span-1">
                            <input type="hidden" name="contact_persons[{{ $i }}][primary]" value="0">
                            <input type="checkbox" name="contact_persons[{{ $i }}][primary]" value="1"
                                   class="checkbox checkbox-xs"
                                   @checked($cp['primary'] ?? false)>
                            <span>{{ __('Primär') }}</span>
                        </label>
                        <button type="button" class="btn btn-ghost btn-xs sm:col-span-1" data-contact-remove>×</button>
                    </div>
                @endforeach
            </div>
            @error('contact_persons')<p class="text-error text-sm mt-1">{{ $message }}</p>@enderror
        </div>

        @php
            $allTags = $allTags ?? collect();
            $selectedTagIds = old('tag_ids', $customer?->tags?->pluck('id')->all() ?? []);
        @endphp
        <div>
            <label class="label"><span class="label-text">{{ __('Tags') }}</span></label>
            @if ($allTags->isNotEmpty())
                <select name="tag_ids[]" multiple size="4" class="select select-bordered w-full">
                    @foreach ($allTags as $tag)
                        <option value="{{ $tag->id }}" @selected(in_array($tag->id, (array) $selectedTagIds))>{{ $tag->name }}</option>
                    @endforeach
                </select>
            @endif
            <input type="text" name="new_tags" value="{{ old('new_tags', '') }}" placeholder="{{ __('Neue Tags (kommagetrennt)') }}"
                   maxlength="500" class="input input-sm input-bordered w-full mt-2">
        </div>

        <div class="flex flex-wrap items-center gap-3 pt-2">
            <button type="submit" class="btn btn-sm btn-primary">{{ $customer ? __('Speichern') : __('Anlegen') }}</button>
            @if ($isDialog)
                <button type="button" class="btn btn-sm btn-ghost" data-entry-modal-close>{{ __('Abbrechen') }}</button>
            @else
                <a href="{{ route('customers.index') }}" class="btn btn-sm btn-ghost">{{ __('Abbrechen') }}</a>
            @endif
        </div>
    </form>
</x-dialog>

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
                // Letzte Zeile: nur leeren
                target.closest('[data-contact-row]')?.querySelectorAll('input').forEach(inp => {
                    if (inp.type === 'checkbox') inp.checked = false;
                    else if (inp.type !== 'hidden') inp.value = '';
                });
            }
        }
    });
})();
</script>
