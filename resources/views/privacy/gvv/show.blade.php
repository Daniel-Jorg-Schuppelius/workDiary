@extends('layouts.app')
@section('title', $gvv->title)
@section('nav-title', $gvv->title)
@php
    $matrixLabels = [
        'information_duties' => __('Informationspflichten (Art. 13/14)'),
        'data_subject_rights' => __('Betroffenenrechte'),
        'incidents' => __('Datenschutzvorfälle'),
        'authority_contact' => __('Aufsichtsbehörde-Kontakt'),
    ];
    $roleOptions = ['us' => __('Wir'), 'partner' => __('Partner'), 'joint' => __('Gemeinsam')];
@endphp
@section('content')
    <x-index-page :subtitle="__('Zuständigkeitsmatrix und verknüpfte Verarbeitungstätigkeiten der gemeinsamen Verantwortlichkeit.')">
        <x-slot:actions>
            <x-icon-btn icon="diversity_3" tone="ghost" size="sm"
                        :href="route('dataprotection.processors.index')"
                        show-label>{{ $gvv->partner?->name }}</x-icon-btn>
        </x-slot:actions>

        @if (session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif

        <x-card>
            <h2 class="font-['Space_Grotesk'] text-base font-semibold">{{ __('Eckdaten') }}
                <x-status-badge tone="ghost" size="sm" class="ml-2">{{ $gvv->status->label() }}</x-status-badge>
            </h2>
            <div class="text-sm space-y-1 mt-2">
                <p><span class="font-semibold">{{ __('Gemeinsame Anlaufstelle') }}:</span> {{ $gvv->contact_point ?? '—' }}</p>
                <p><span class="font-semibold">{{ __('Wesentliches bereitgestellt') }}:</span> {{ $gvv->essence_provided ? __('ja') : __('nein') }}</p>
                @if ($gvv->document_path)<p><a class="link" href="{{ route('dataprotection.gvv.document', $gvv) }}">{{ __('Vertragsdokument') }}: {{ $gvv->document_name }}</a></p>@endif
            </div>
        </x-card>

        @can('update', $gvv)
            <x-card>
                <h2 class="font-['Space_Grotesk'] text-base font-semibold">{{ __('Zuständigkeitsmatrix') }}</h2>
                <form method="post" action="{{ route('dataprotection.gvv.update', $gvv) }}" class="space-y-2 mt-2">
                    @csrf @method('PUT')
                    @foreach ($matrixKeys as $k)
                        <div class="flex items-center justify-between gap-2">
                            <span class="text-sm flex-1">{{ $matrixLabels[$k] ?? $k }}</span>
                            <select name="responsibilities[{{ $k }}]" class="select select-sm select-bordered">
                                @foreach ($roleOptions as $v => $l)<option value="{{ $v }}" @selected(data_get($gvv->responsibilities, $k) === $v)>{{ $l }}</option>@endforeach
                            </select>
                        </div>
                    @endforeach
                    <div class="flex items-center gap-3 pt-1">
                        <input name="contact_point" class="input input-sm input-bordered flex-1" value="{{ $gvv->contact_point }}" placeholder="{{ __('Anlaufstelle') }}">
                        <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="essence_provided" value="1" class="checkbox checkbox-sm" @checked($gvv->essence_provided)> {{ __('Wesentliches bereitgestellt') }}</label>
                        <select name="status" class="select select-sm select-bordered">
                            @foreach (\App\Enums\Privacy\AgreementStatus::cases() as $s)<option value="{{ $s->value }}" @selected($gvv->status === $s)>{{ $s->label() }}</option>@endforeach
                        </select>
                    </div>
                    <x-icon-btn icon="check" tone="primary" size="sm" type="submit" show-label>{{ __('Speichern') }}</x-icon-btn>
                </form>
            </x-card>

            <x-card>
                <h2 class="font-['Space_Grotesk'] text-base font-semibold">{{ __('Verknüpfte Verarbeitungstätigkeiten') }}</h2>
                <form method="post" action="{{ route('dataprotection.gvv.activities', $gvv) }}" class="space-y-2 mt-2">
                    @csrf
                    <div class="grid md:grid-cols-2 gap-1">
                        @foreach ($allActivities as $act)
                            <label class="flex items-center gap-2 text-sm">
                                <input type="checkbox" name="activity_ids[]" value="{{ $act->id }}" class="checkbox checkbox-sm" @checked(in_array($act->id, $linkedIds, true))> {{ $act->name }}
                            </label>
                        @endforeach
                    </div>
                    <x-icon-btn icon="check" tone="primary" size="sm" type="submit" show-label>{{ __('Verknüpfungen speichern') }}</x-icon-btn>
                </form>
            </x-card>
        @endcan
    </x-index-page>
@endsection
