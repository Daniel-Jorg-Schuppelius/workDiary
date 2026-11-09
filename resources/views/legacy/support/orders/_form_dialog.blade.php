{{--
  Created on   : Mon Nov 09 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _form_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Support-Auftrag anlegen bzw. bearbeiten. Erwartet: $order, $isEdit, $customers, $selectedCustomer, $orderer, $mechanics --}}
@php
    $statuses = \App\Legacy\Models\LegacySupportOrder::STATUSES;
    $customerSqid = fn (int $id): string => \App\Support\Sqid::encode(\App\Legacy\Models\LegacySupportCustomer::class, $id);
    $currentCustomer = (string) old('kunde', $selectedCustomer);
    $officeOrder = $isEdit && $order->isOfficeOrder();
    $appointmentValue = old('termin', $order?->appointmentAt()?->format('Y-m-d\TH:i'));
    $inputClass = 'input input-bordered input-sm w-full';
@endphp
<x-modal
    :title="$isEdit ? __('legacy_support.orders.edit_title', ['number' => $order->id]) : __('legacy_support.orders.create')"
    :eyebrow="__('legacy_support.orders.eyebrow')"
    icon="support"
    :badge="$isEdit ? $order->statusLabel() : __('legacy_support.status.new')"
    :badge-tone="$isEdit ? $order->statusTone() : 'info'"
    tone="primary"
    :action="$isEdit ? route('legacy.support.orders.update', $order) : route('legacy.support.orders.store')"
    :method="$isEdit ? 'PUT' : 'POST'"
    :form-data="['data-entry-form' => '']"
    :submit-label="$isEdit ? __('legacy_support.save') : __('legacy_support.orders.create')">

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <div class="sm:col-span-2">
            <label for="support-kunde" class="mb-1 block text-sm font-medium">{{ __('legacy_support.field.customer') }}<span class="text-error">*</span></label>
            <select id="support-kunde" name="kunde" class="select select-bordered select-sm w-full @error('kunde') select-error @enderror" required>
                <option value="">{{ __('legacy_support.choose_customer') }}</option>
                @foreach ($customers as $customer)
                    @php $sqid = $customerSqid((int) $customer->id); @endphp
                    <option value="{{ $sqid }}" @selected($currentCustomer === $sqid)>{{ $customer->kunde }}</option>
                @endforeach
            </select>
            @error('kunde')<p class="mt-1 text-sm text-error">{{ $message }}</p>@enderror
        </div>

        <div>
            <label for="support-kname" class="mb-1 block text-sm font-medium">{{ __('legacy_support.field.orderer') }}</label>
            @if ($isEdit && ! $officeOrder)
                <input id="support-kname" name="kname" type="text" maxlength="30" value="{{ old('kname', $order->kname) }}" class="{{ $inputClass }} @error('kname') input-error @enderror">
                @error('kname')<p class="mt-1 text-sm text-error">{{ $message }}</p>@enderror
            @else
                <input id="support-kname" type="text" value="{{ $orderer }}" class="{{ $inputClass }}" readonly aria-readonly="true">
                <p class="mt-1 text-xs text-muted">{{ __('legacy_support.orders.orderer_office_hint') }}</p>
            @endif
        </div>

        <div>
            <label for="support-telefon" class="mb-1 block text-sm font-medium">{{ __('legacy_support.field.phone') }}</label>
            <input id="support-telefon" name="telefon" type="text" maxlength="30" value="{{ old('telefon', $order?->telefon) }}" class="{{ $inputClass }} @error('telefon') input-error @enderror">
            @error('telefon')<p class="mt-1 text-sm text-error">{{ $message }}</p>@enderror
        </div>

        <div class="sm:col-span-2">
            <label for="support-objekt" class="mb-1 block text-sm font-medium">{{ __('legacy_support.field.object') }}</label>
            <input id="support-objekt" name="objekt" type="text" maxlength="255" value="{{ old('objekt', $order?->objekt) }}" class="{{ $inputClass }} @error('objekt') input-error @enderror">
            @error('objekt')<p class="mt-1 text-sm text-error">{{ $message }}</p>@enderror
        </div>

        <div class="sm:col-span-2">
            <label for="support-auftrag" class="mb-1 block text-sm font-medium">{{ __('legacy_support.field.order_text') }}<span class="text-error">*</span></label>
            <textarea id="support-auftrag" name="auftrag" rows="4" class="textarea textarea-bordered textarea-sm w-full @error('auftrag') textarea-error @enderror" required>{{ old('auftrag', $order?->auftrag) }}</textarea>
            @error('auftrag')<p class="mt-1 text-sm text-error">{{ $message }}</p>@enderror
        </div>

        @if ($isEdit)
            <div>
                <label for="support-termin" class="mb-1 block text-sm font-medium">{{ __('legacy_support.field.appointment') }}</label>
                <input id="support-termin" name="termin" type="datetime-local" value="{{ $appointmentValue }}" class="{{ $inputClass }} @error('termin') input-error @enderror">
                <p class="mt-1 text-xs text-muted">{{ __('legacy_support.orders.appointment_hint') }}</p>
                @error('termin')<p class="mt-1 text-sm text-error">{{ $message }}</p>@enderror
            </div>

            <div>
                <label for="support-wer" class="mb-1 block text-sm font-medium">{{ __('legacy_support.field.mechanic') }}</label>
                <select id="support-wer" name="wer" class="select select-bordered select-sm w-full @error('wer') select-error @enderror">
                    <option value="">{{ __('legacy_support.no_mechanic') }}</option>
                    @foreach ($mechanics as $mechanic)
                        <option value="{{ $mechanic }}" @selected((string) old('wer', $order->wer) === $mechanic)>{{ $mechanic }}</option>
                    @endforeach
                </select>
                @error('wer')<p class="mt-1 text-sm text-error">{{ $message }}</p>@enderror
            </div>

            <div class="sm:col-span-2">
                <label for="support-antwort" class="mb-1 block text-sm font-medium">{{ __('legacy_support.field.reply') }}</label>
                <textarea id="support-antwort" name="antwort" rows="3" class="textarea textarea-bordered textarea-sm w-full">{{ old('antwort', $order->antwort) }}</textarea>
                <p class="mt-1 text-xs text-muted">{{ __('legacy_support.orders.reply_hint') }}</p>
            </div>

            <div class="sm:col-span-2">
                <label for="support-intern" class="mb-1 block text-sm font-medium">{{ __('legacy_support.field.internal_note') }}</label>
                <textarea id="support-intern" name="intern" rows="3" class="textarea textarea-bordered textarea-sm w-full">{{ old('intern', $order->intern) }}</textarea>
            </div>

            <div>
                <label for="support-dauer" class="mb-1 block text-sm font-medium">{{ __('legacy_support.field.duration') }}</label>
                <input id="support-dauer" name="dauer" type="text" maxlength="255" value="{{ old('dauer', $order->dauer) }}" class="{{ $inputClass }} @error('dauer') input-error @enderror">
                @error('dauer')<p class="mt-1 text-sm text-error">{{ $message }}</p>@enderror
            </div>

            <div>
                <label for="support-status" class="mb-1 block text-sm font-medium">{{ __('legacy_support.field.status') }}<span class="text-error">*</span></label>
                <select id="support-status" name="status" class="select select-bordered select-sm w-full @error('status') select-error @enderror" required>
                    @foreach ($statuses as $status)
                        <option value="{{ $status }}" @selected((int) old('status', $order->status) === $status)>{{ \App\Legacy\Models\LegacySupportOrder::statusLabelFor($status) }}</option>
                    @endforeach
                </select>
                @error('status')<p class="mt-1 text-sm text-error">{{ $message }}</p>@enderror
            </div>
        @endif
    </div>
</x-modal>
