@extends('layouts.app')

@section('title', __('Branding'))
@section('nav-title', __('Branding'))

@section('content')
@php
    /** @var \App\Models\Organization $organization */
    /** @var \App\Services\BrandingService $branding */
    $settings = $branding->settings();
    $contact = (array) ($settings['contact'] ?? []);
    $legal = (array) ($settings['legal'] ?? []);
    $colors = (array) ($settings['colors'] ?? []);
    $pdfCfg = (array) ($settings['pdf'] ?? []);
    $pdfTypes = array_keys((array) config('branding.pdf', []));
    $logoMaxKb = (int) config('branding.limits.logo_kb', 2048);
    $logoHelper = __('PNG, JPG oder WEBP. Max. :max KB.', ['max' => $logoMaxKb]);
@endphp

<x-page-shell>
    @if (session('success'))
        <div class="alert alert-success">
            <x-icon name="check_circle" /> <span>{{ session('success') }}</span>
        </div>
    @endif

    {{-- ── Logos (eigene Forms, deshalb außerhalb des Settings-Forms) ── --}}
    <div class="card bg-base-100 shadow">
        <div class="card-body">
            <h2 class="card-title">
                <x-icon name="image" />
                {{ __('Logos') }}
            </h2>
            <p class="text-sm opacity-70 mb-2">
                {{ __('Diese Logos erscheinen im Webinterface, in PDFs und auf der Login-Seite.') }}
            </p>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <x-file-upload
                    :label="__('Logo (helle Variante)')"
                    :action="route('attachments.store', ['type' => 'organization', 'id' => $organization->sqid])"
                    :delete-action="route('attachments.destroyMeta', ['type' => 'organization', 'id' => $organization->sqid, 'meta' => 'logo'])"
                    :current="$organization->logo()"
                    :meta="\App\Models\Attachment::META_LOGO"
                    :max-kb="$logoMaxKb"
                    :helper="$logoHelper"
                />
                <x-file-upload
                    :label="__('Logo (dunkle Variante, optional)')"
                    :action="route('attachments.store', ['type' => 'organization', 'id' => $organization->sqid])"
                    :delete-action="route('attachments.destroyMeta', ['type' => 'organization', 'id' => $organization->sqid, 'meta' => 'logo_dark'])"
                    :current="$organization->logoDark()"
                    :meta="\App\Models\Attachment::META_LOGO_DARK"
                    :max-kb="$logoMaxKb"
                    :helper="$logoHelper"
                />
            </div>
        </div>
    </div>

    {{-- ── Restliche Settings als ein Form ───────────────────────────── --}}
    <form method="POST" action="{{ route('admin.branding.update') }}" class="space-y-6">
        @csrf
        @method('PUT')

        <x-form-group :legend="__('Stammdaten')" icon="badge" tone="primary" cols="2">
            <x-input-field name="branding[app_name]" :label="__('Anzeigename der App')" maxlength="120"
                           span="2" error="branding.app_name"
                           :value="old('branding.app_name', data_get($organization->settings, 'branding.app_name', ''))"
                           :placeholder="config('branding.app_name') ?? config('app.name')" />
            <x-input-field name="branding[slogan]" :label="__('Slogan / Untertitel')" maxlength="200"
                           span="2"
                           :value="old('branding.slogan', data_get($organization->settings, 'branding.slogan', ''))" />
        </x-form-group>

        <x-form-group :legend="__('Kontakt')" icon="contact_mail" tone="ghost" cols="2">
            @foreach ([
                'street'      => __('Straße'),
                'postal_code' => __('PLZ'),
                'city'        => __('Stadt'),
                'country'     => __('Land'),
                'phone'       => __('Telefon'),
                'email'       => __('E-Mail'),
                'web'         => __('Web'),
            ] as $field => $label)
                <div class="fieldset">
                    <label class="fieldset-label">{{ $label }}</label>
                    <input type="{{ $field === 'email' ? 'email' : ($field === 'web' ? 'url' : 'text') }}"
                           name="branding[contact][{{ $field }}]"
                           class="input input-bordered w-full @error('branding.contact.'.$field) input-error @enderror"
                           value="{{ old('branding.contact.'.$field, data_get($organization->settings, 'branding.contact.'.$field, '')) }}">
                    @error('branding.contact.'.$field)<p class="text-error text-xs mt-1">{{ $message }}</p>@enderror
                </div>
            @endforeach
        </x-form-group>

        <x-form-group :legend="__('Rechtliches & PDF-Fuß')" icon="gavel" tone="ghost" cols="2">
            @foreach ([
                'vat_id'         => __('USt-IdNr.'),
                'tax_number'     => __('Steuernummer'),
                'account_holder' => __('Kontoinhaber'),
                'bank_name'      => __('Bank'),
                'iban'           => __('IBAN'),
                'bic'            => __('BIC'),
                'register'       => __('Handelsregister'),
            ] as $field => $label)
                <div class="fieldset">
                    <label class="fieldset-label">{{ $label }}</label>
                    <input type="text" name="branding[legal][{{ $field }}]"
                           class="input input-bordered w-full"
                           value="{{ old('branding.legal.'.$field, data_get($organization->settings, 'branding.legal.'.$field, '')) }}">
                </div>
            @endforeach
            <x-textarea-field name="branding[legal][footer_text]" :label="__('Fußzeilentext (für PDF-Dokumente)')" rows="3"
                              span="2" :value="old('branding.legal.footer_text', data_get($organization->settings, 'branding.legal.footer_text', ''))" />
        </x-form-group>

        <x-form-group :legend="__('Farben')" icon="palette" tone="ghost" cols="2">
            <div class="fieldset">
                <label class="fieldset-label">{{ __('Primärfarbe') }}</label>
                <input type="color"
                       name="branding[colors][primary]"
                       class="input input-bordered w-full h-12 p-1"
                       value="{{ old('branding.colors.primary', data_get($organization->settings, 'branding.colors.primary') ?: ($colors['primary'] ?? '#0ea5e9')) }}">
                @error('branding.colors.primary')<p class="text-error text-xs mt-1">{{ $message }}</p>@enderror
            </div>
            <div class="fieldset">
                <label class="fieldset-label">{{ __('Akzentfarbe') }}</label>
                <input type="color"
                       name="branding[colors][accent]"
                       class="input input-bordered w-full h-12 p-1"
                       value="{{ old('branding.colors.accent', data_get($organization->settings, 'branding.colors.accent') ?: ($colors['accent'] ?? '#22d3ee')) }}">
                @error('branding.colors.accent')<p class="text-error text-xs mt-1">{{ $message }}</p>@enderror
            </div>
        </x-form-group>

        <x-form-group :legend="__('PDF-Konfiguration je Dokumenttyp')" icon="picture_as_pdf" tone="ghost" cols="1">
            <div class="overflow-x-auto">
                <table class="table table-sm">
                    <thead>
                        <tr>
                            <th>{{ __('Dokumenttyp') }}</th>
                            <th>{{ __('Logo') }}</th>
                            <th>{{ __('Kontakt im Header') }}</th>
                            <th>{{ __('Fußzeile') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($pdfTypes as $type)
                            @php
                                $cur = (array) data_get($organization->settings, 'branding.pdf.'.$type, []);
                                $logoChoice = old('branding.pdf.'.$type.'.logo', $cur['logo'] ?? 'light');
                                $showContact = (bool) old('branding.pdf.'.$type.'.show_contact', $cur['show_contact'] ?? true);
                                $showFooter = (bool) old('branding.pdf.'.$type.'.show_footer', $cur['show_footer'] ?? true);
                            @endphp
                            <tr>
                                <td class="font-medium">{{ __('branding.pdf.'.$type, [], app()->getLocale()) !== 'branding.pdf.'.$type ? __('branding.pdf.'.$type) : ucfirst($type) }}</td>
                                <td>
                                    <select name="branding[pdf][{{ $type }}][logo]"
                                            class="select select-bordered select-sm">
                                        <option value="light" @selected($logoChoice === 'light')>{{ __('Helle Variante') }}</option>
                                        <option value="dark" @selected($logoChoice === 'dark')>{{ __('Dunkle Variante') }}</option>
                                        <option value="none" @selected($logoChoice === 'none')>{{ __('Kein Logo') }}</option>
                                    </select>
                                </td>
                                <td>
                                    <input type="checkbox" class="toggle toggle-sm"
                                           name="branding[pdf][{{ $type }}][show_contact]" value="1" @checked($showContact)>
                                </td>
                                <td>
                                    <input type="checkbox" class="toggle toggle-sm"
                                           name="branding[pdf][{{ $type }}][show_footer]" value="1" @checked($showFooter)>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-form-group>

        <div class="flex justify-end gap-2">
            <x-button type="submit" tone="primary" size="md" icon="save">{{ __('Speichern') }}</x-button>
        </div>
    </form>
</x-page-shell>
@endsection
