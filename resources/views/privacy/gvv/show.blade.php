@extends('layouts.app')
@section('title', $gvv->title)
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
    <div class="p-4 max-w-4xl space-y-4">
        <div class="flex items-center justify-between">
            <h1 class="text-xl font-semibold">{{ $gvv->title }} <span class="badge badge-ghost ml-2">{{ $gvv->status->label() }}</span></h1>
            <span class="text-sm text-base-content/70">{{ $gvv->partner?->name }}</span>
        </div>
        @if (session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif

        <section class="card bg-base-200 p-4 text-sm space-y-1">
            <p><span class="font-semibold">{{ __('Gemeinsame Anlaufstelle') }}:</span> {{ $gvv->contact_point ?? '—' }}</p>
            <p><span class="font-semibold">{{ __('Wesentliches bereitgestellt') }}:</span> {{ $gvv->essence_provided ? __('ja') : __('nein') }}</p>
            @if ($gvv->document_path)<p><a class="link" href="{{ route('dataprotection.gvv.document', $gvv) }}">{{ __('Vertragsdokument') }}: {{ $gvv->document_name }}</a></p>@endif
        </section>

        @can('update', $gvv)
            <section class="card bg-base-200 p-4 space-y-3">
                <h2 class="font-semibold">{{ __('Zuständigkeitsmatrix') }}</h2>
                <form method="post" action="{{ route('dataprotection.gvv.update', $gvv) }}" class="space-y-2">
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
                    <button class="btn btn-sm btn-primary">{{ __('Speichern') }}</button>
                </form>
            </section>

            <section class="card bg-base-200 p-4 space-y-2">
                <h2 class="font-semibold">{{ __('Verknüpfte Verarbeitungstätigkeiten') }}</h2>
                <form method="post" action="{{ route('dataprotection.gvv.activities', $gvv) }}" class="space-y-2">
                    @csrf
                    <div class="grid md:grid-cols-2 gap-1">
                        @foreach ($allActivities as $act)
                            <label class="flex items-center gap-2 text-sm">
                                <input type="checkbox" name="activity_ids[]" value="{{ $act->id }}" class="checkbox checkbox-sm" @checked(in_array($act->id, $linkedIds, true))> {{ $act->name }}
                            </label>
                        @endforeach
                    </div>
                    <button class="btn btn-sm">{{ __('Verknüpfungen speichern') }}</button>
                </form>
            </section>
        @endcan
    </div>
@endsection
