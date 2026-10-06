{{--
  Created on   : Sun Sep 27 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : supplier-questionnaire.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Öffentliche Selbstauskunft (MVP-937). Erwartet: $sent, $request, $orgName, $token --}}
<x-public-page :title="__('supplier_questionnaire.public_title', ['org' => $orgName])" main="max-w-2xl mx-auto p-4 flex flex-col gap-4">
    <x-card class="flex flex-col gap-1">
        <h1 class="card-title">{{ __('supplier_questionnaire.public_title', ['org' => $orgName]) }}</h1>
        @if ($request)
            <p class="text-sm font-medium">{{ $request->questionnaire?->name }}</p>
            @if ($request->questionnaire?->description)<p class="text-sm opacity-70 whitespace-pre-line">{{ $request->questionnaire->description }}</p>@endif
            @if ($request->note)<div role="alert" class="alert alert-warning text-sm">{{ __('supplier_questionnaire.public_rework', ['note' => $request->note]) }}</div>@endif
        @endif
    </x-card>
    @if ($sent)
        <div role="status" class="alert alert-success">{{ __('supplier_questionnaire.public_thanks') }}</div>
    @else
        <x-card>
            <form method="POST" action="{{ route('supplier-questionnaire.public.store', $token) }}" class="flex flex-col gap-3">
                @csrf
                @foreach ($request->schema_snapshot as $field)
                    <x-field-input :field="$field" :value="$request->answers->get($field->key)" />
                @endforeach
                <div class="flex justify-end"><x-button type="submit">{{ __('supplier_questionnaire.public_submit') }}</x-button></div>
            </form>
        </x-card>
    @endif
</x-public-page>
