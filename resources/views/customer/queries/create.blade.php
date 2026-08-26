{{--
  Created on   : Mon Aug 10 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : create.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Rückfrage stellen (MVP-512) — erwartet: $subject, $subjectLabel, $subjectType, $subjectSqid --}}
@extends('customer.layout')

@section('content')
    <div class="mx-auto max-w-xl">
        <div class="rounded-box border border-base-300 bg-base-100 p-6">
            <h1 class="mb-1 text-xl font-semibold">{{ __('Rückfrage stellen') }}</h1>
            <p class="mb-4 text-sm text-base-content/70">{{ $subjectLabel }}</p>

            <form method="POST" action="{{ route('customer.queries.store') }}" enctype="multipart/form-data" class="space-y-3">
                @csrf
                <input type="hidden" name="subject_type" value="{{ $subjectType }}">
                <input type="hidden" name="subject" value="{{ $subjectSqid }}">

                <div class="fieldset">
                    <label class="fieldset-label" for="query-question">{{ __('Ihre Rückfrage oder Ihr Kommentar') }}</label>
                    <textarea id="query-question" name="question" rows="5" required maxlength="2000"
                              class="textarea textarea-bordered w-full">{{ old('question') }}</textarea>
                    <p class="mt-1 text-xs text-muted">{{ __('Nach dem Absenden ist der Text aus Nachweisgründen nicht änderbar.') }}</p>
                    @error('question')<p class="text-error text-sm">{{ $message }}</p>@enderror
                </div>

                {{-- Anhänge (MVP-712): Upload-Policy wie Portal-Tickets, nach dem Absenden unveränderlich. --}}
                <div class="fieldset">
                    <label class="fieldset-label" for="query-files">{{ __('Anhänge (optional)') }}</label>
                    <input id="query-files" name="files[]" type="file" multiple class="file-input file-input-sm file-input-bordered w-full">
                    <p class="mt-1 text-xs text-muted">{{ __('Bis zu :max Dateien, je max. :mb MB (PDF, Bilder, Office, Text, ZIP).', ['max' => \App\Http\Controllers\CustomerPortal\QueryController::MAX_FILES, 'mb' => \App\Services\Attachments\FileAttacher::maxMb()]) }}</p>
                    @error('files')<p class="text-error text-sm">{{ $message }}</p>@enderror
                    @error('files.*')<p class="text-error text-sm">{{ $message }}</p>@enderror
                </div>

                <div class="flex justify-end gap-2">
                    <a href="{{ url()->previous(route('customer.queries.index')) }}" class="btn btn-ghost">{{ __('Abbrechen') }}</a>
                    <x-button type="submit" tone="primary" icon="send"><span>{{ __('Absenden') }}</span></x-button>
                </div>
            </form>
        </div>
    </div>
@endsection
