@extends('layouts.app')
@section('title', __('GVV-Register'))
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
    <div class="p-4 space-y-4">
        <div class="flex items-center justify-between">
            <h1 class="text-xl font-semibold">{{ __('Gemeinsame Verantwortlichkeit (Art. 26)') }}</h1>
            <a href="{{ route('dataprotection.processors.index') }}" class="btn btn-sm">{{ __('Dienstleister') }}</a>
        </div>
        @if (session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif
        @if ($errors->any())<div class="alert alert-error"><ul class="list-disc ml-4">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif

        <div class="overflow-x-auto rounded-box border border-base-300">
            <table class="table table-sm">
                <thead><tr><th>{{ __('Titel') }}</th><th>{{ __('Partner') }}</th><th>{{ __('Status') }}</th><th>{{ __('Wesentliches bereitgestellt') }}</th></tr></thead>
                <tbody>
                    @forelse ($agreements as $g)
                        <tr class="hover">
                            <td><a class="link" href="{{ route('dataprotection.gvv.show', $g) }}">{{ $g->title }}</a></td>
                            <td>{{ $g->partner?->name ?? '—' }}</td>
                            <td><span class="badge badge-ghost">{{ $g->status->label() }}</span></td>
                            <td>{{ $g->essence_provided ? __('ja') : '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="text-center text-base-content/60 py-6">{{ __('Keine GVV erfasst.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        {{ $agreements->links() }}

        @can('create', \App\Models\Privacy\JointControllerAgreement::class)
            <section class="card bg-base-200 p-4 space-y-3">
                <h2 class="font-semibold">{{ __('Neue GVV anlegen') }}</h2>
                <form method="post" action="{{ route('dataprotection.gvv.store') }}" enctype="multipart/form-data" class="space-y-2">
                    @csrf
                    <div class="grid md:grid-cols-2 gap-2">
                        <select name="partner_id" class="select select-sm select-bordered" required>
                            <option value="">{{ __('Partner (Dienstleister) …') }}</option>
                            @foreach ($partners as $p)<option value="{{ $p->id }}">{{ $p->name }}</option>@endforeach
                        </select>
                        <input name="title" class="input input-sm input-bordered" placeholder="{{ __('Titel') }}" required>
                        <input name="valid_from" type="date" class="input input-sm input-bordered" title="{{ __('Gültig ab') }}">
                        <input name="contact_point" class="input input-sm input-bordered" placeholder="{{ __('Gemeinsame Anlaufstelle') }}">
                    </div>
                    <div class="space-y-1">
                        <p class="text-sm font-semibold">{{ __('Zuständigkeitsmatrix') }}</p>
                        @foreach ($matrixKeys as $k)
                            <div class="flex items-center justify-between gap-2">
                                <span class="text-sm flex-1">{{ $matrixLabels[$k] ?? $k }}</span>
                                <select name="responsibilities[{{ $k }}]" class="select select-xs select-bordered">
                                    @foreach ($roleOptions as $v => $l)<option value="{{ $v }}">{{ $l }}</option>@endforeach
                                </select>
                            </div>
                        @endforeach
                    </div>
                    <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="essence_provided" value="1" class="checkbox checkbox-sm"> {{ __('Wesentliches der GVV den Betroffenen bereitgestellt') }}</label>
                    <input type="file" name="document" class="file-input file-input-sm file-input-bordered w-full" accept=".pdf,.doc,.docx">
                    <button class="btn btn-sm btn-primary">{{ __('GVV anlegen') }}</button>
                </form>
            </section>
        @endcan
    </div>
@endsection
