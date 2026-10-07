{{--
  Created on   : Wed Oct 07 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : create.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{--
  Leistung anfragen (MVP-1074/1077) — erwartet: $kind, $schema, $assets, $submissionKey, $action,
  $catalogItem (?RequestItem), $catalogSchema (?FieldSchema). Ohne JS normaler POST, mit JS
  Upload mit Fortschritt (upload-form.js).
--}}
@extends('customer.layout')

@section('content')
@php
    $conditions = fn ($fields) => collect($fields->all())->filter(fn ($f) => $f->hasCondition())->mapWithKeys(fn ($f) => [$f->key => $f->visibleIf])->all();
    $initial = fn ($fields, string $prefix) => collect($fields->all())->mapWithKeys(fn ($f) => [$f->key => is_scalar(old("{$prefix}.{$f->key}")) ? (string) old("{$prefix}.{$f->key}") : ''])->all();
@endphp
    <div class="mx-auto max-w-2xl">
        <x-card padding="p-6">
            <h1 class="mb-1 text-xl font-semibold">{{ $catalogItem !== null ? __('customer_intake.portal.request_catalog', ['item' => $catalogItem->name]) : __('customer_intake.portal.create_title', ['kind' => $kind->label()]) }}</h1>
            <p class="mb-4 text-sm text-base-content/70">{{ __('customer_intake.portal.create_intro') }}</p>

            <form method="POST" action="{{ $action }}" enctype="multipart/form-data" class="space-y-4" data-upload-form>
                @csrf
                <input type="hidden" name="kind" value="{{ $kind->value }}">
                <input type="hidden" name="submission_key" value="{{ old('submission_key', $submissionKey) }}">

                <div class="fieldset">
                    <label class="fieldset-label" for="intake-subject">{{ __('customer_intake.field.subject') }} *</label>
                    <input id="intake-subject" name="subject" type="text" required maxlength="200" value="{{ old('subject', $catalogItem?->name) }}"
                           class="input input-bordered w-full @error('subject') input-error @enderror">
                    @error('subject')<p class="text-error text-sm">{{ $message }}</p>@enderror
                </div>

                <div class="fieldset">
                    <label class="fieldset-label" for="intake-description">{{ __('customer_intake.field.description') }}</label>
                    <textarea id="intake-description" name="description" rows="4" maxlength="5000"
                              class="textarea textarea-bordered w-full @error('description') textarea-error @enderror">{{ old('description') }}</textarea>
                    @if ($kind === \App\Enums\Customer\IntakeKind::It)
                        <p class="mt-1 text-xs text-warning">{{ __('customer_intake.portal.no_passwords') }}</p>
                    @endif
                    @error('description')<p class="text-error text-sm">{{ $message }}</p>@enderror
                </div>

                <div class="fieldset">
                    <label class="fieldset-label" for="intake-desired-date">{{ __('customer_intake.field.desired_date') }}</label>
                    <input id="intake-desired-date" name="desired_date" type="date" min="{{ now()->toDateString() }}" value="{{ old('desired_date') }}"
                           class="input input-bordered w-full sm:w-56 @error('desired_date') input-error @enderror" aria-describedby="intake-desired-date-hint">
                    <p id="intake-desired-date-hint" class="mt-1 text-xs text-muted">{{ __('customer_intake.portal.desired_date_hint') }}</p>
                    @error('desired_date')<p class="text-error text-sm">{{ $message }}</p>@enderror
                </div>

                <fieldset class="grid gap-3 sm:grid-cols-2" x-data="formFill" data-conditions="{{ json_encode($conditions($schema)) }}" data-initial="{{ json_encode($initial($schema, 'values')) }}"
                          @input.capture="track($event)" @change.capture="track($event)">
                    <legend class="mb-1 text-sm font-semibold">{{ __('customer_intake.portal.details', ['kind' => $kind->label()]) }}</legend>
                    @foreach ($schema as $field)
                        @if ($field->hasCondition())
                            <div class="contents" x-show="visible('{{ $field->key }}')" x-cloak>
                        @endif
                        <x-field-input :field="$field" />
                        @if ($field->hasCondition())
                            </div>
                        @endif
                    @endforeach
                    @foreach ($schema as $field)
                        @error('values.' . $field->key)<p class="text-error text-sm sm:col-span-2">{{ $message }}</p>@enderror
                    @endforeach
                </fieldset>

                @if ($catalogSchema !== null && ! $catalogSchema->isEmpty())
                    <fieldset class="grid gap-3 sm:grid-cols-2" x-data="formFill" data-prefix="catalog" data-conditions="{{ json_encode($conditions($catalogSchema)) }}" data-initial="{{ json_encode($initial($catalogSchema, 'catalog')) }}"
                              @input.capture="track($event)" @change.capture="track($event)">
                        <legend class="mb-1 text-sm font-semibold">{{ $catalogItem->name }}</legend>
                        @foreach ($catalogSchema as $field)
                            @if ($field->hasCondition())
                                <div class="contents" x-show="visible('{{ $field->key }}')" x-cloak>
                            @endif
                            <x-field-input :field="$field" prefix="catalog" />
                            @if ($field->hasCondition())
                                </div>
                            @endif
                        @endforeach
                        @foreach ($catalogSchema as $field)
                            @error('catalog.' . $field->key)<p class="text-error text-sm sm:col-span-2">{{ $message }}</p>@enderror
                            @error('files.' . $field->key)<p class="text-error text-sm sm:col-span-2">{{ $message }}</p>@enderror
                        @endforeach
                    </fieldset>
                @endif

                @if ($assets->isNotEmpty())
                    <div class="fieldset">
                        <label class="fieldset-label" for="intake-asset">{{ __('customer_intake.field.asset_optional') }}</label>
                        <select id="intake-asset" name="asset" class="select select-bordered w-full">
                            <option value="">{{ __('customer_intake.portal.no_asset') }}</option>
                            @foreach ($assets as $asset)
                                <option value="{{ $asset->sqid }}" @selected(old('asset') === $asset->sqid)>{{ $asset->name }}</option>
                            @endforeach
                        </select>
                    </div>
                @endif

                <x-upload-input :purpose="$kind->uploadPurpose()" :label="__('customer_intake.field.files_optional')" />

                <p class="text-xs text-muted">{{ __('customer_intake.portal.confirmation_hint') }}</p>

                <div class="flex justify-end gap-2">
                    <x-button :href="route('customer.intakes.index')" tone="ghost" size="md">{{ __('Abbrechen') }}</x-button>
                    <x-button type="submit" tone="primary" icon="send"><span>{{ __('customer_intake.portal.submit') }}</span></x-button>
                </div>
            </form>
        </x-card>
    </div>
@endsection
