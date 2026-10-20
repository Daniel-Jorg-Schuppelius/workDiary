{{--
  Created on   : Wed Oct 07 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : upload.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Dateien nachreichen (MVP-1074): nur eigene, dafür freigegebene Eingänge — erwartet: $targets --}}
@extends('customer.layout')

@section('content')
    <div class="mx-auto max-w-xl space-y-4">
        <x-page-toolbar :title="__('customer_intake.portal.upload_files')" :subtitle="__('customer_intake.portal.upload_intro')" :back="route('customer.intakes.index')" :back-label="__('customer_intake.portal.title')" />
        <x-card padding="p-6">

            <form method="POST" action="{{ route('customer.intakes.upload.store') }}" enctype="multipart/form-data" class="space-y-4" data-upload-form>
                @csrf
                <div class="fieldset">
                    <label class="fieldset-label" for="upload-intake">{{ __('customer_intake.portal.upload_target') }} *</label>
                    <select id="upload-intake" name="intake" required class="select select-bordered w-full @error('intake') select-error @enderror">
                        <option value="">…</option>
                        @foreach ($targets as $target)
                            <option value="{{ $target->sqid }}" @selected(old('intake', request('intake')) === $target->sqid)>{{ $target->number }} — {{ $target->subject }}</option>
                        @endforeach
                    </select>
                    @error('intake')<p class="text-error text-sm">{{ $message }}</p>@enderror
                </div>

                {{-- Druckdaten haben die größte Positivliste; der Eingang prüft serverseitig je Leistungsart. --}}
                <x-upload-input :purpose="$targets->contains(fn ($t) => $t->kind === \App\Enums\Customer\IntakeKind::Print) ? \App\Enums\Attachments\UploadPurpose::PrintData : \App\Enums\Attachments\UploadPurpose::General" required />

                <div class="flex justify-end gap-2">
                    <x-button :href="route('customer.intakes.index')" tone="ghost" size="md">{{ __('Abbrechen') }}</x-button>
                    <x-button type="submit" tone="primary" icon="upload"><span>{{ __('customer_intake.portal.upload_submit') }}</span></x-button>
                </div>
            </form>
        </x-card>
    </div>
@endsection
