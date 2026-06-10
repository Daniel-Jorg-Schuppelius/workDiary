@extends('layouts.app')
@section('title', __('GVV-Register'))
@section('nav-title', __('Gemeinsame Verantwortlichkeit (Art. 26)'))
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
    <x-index-page :subtitle="__('Vereinbarungen über gemeinsame Verantwortlichkeit (Art. 26 DSGVO) verwalten.')">
        <x-slot:actions>
            <x-icon-btn icon="diversity_3" tone="ghost" size="sm"
                        :href="route('dataprotection.processors.index')"
                        show-label>{{ __('Dienstleister') }}</x-icon-btn>
        </x-slot:actions>

        @if (session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif
        @if ($errors->any())<div class="alert alert-error"><ul class="list-disc ml-4">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif

        <x-card padding="p-0">
            <x-table>
                <x-slot:head>
                    <tr>
                        <x-table.th>{{ __('Titel') }}</x-table.th>
                        <x-table.th>{{ __('Partner') }}</x-table.th>
                        <x-table.th>{{ __('Status') }}</x-table.th>
                        <x-table.th>{{ __('Wesentliches bereitgestellt') }}</x-table.th>
                    </tr>
                </x-slot:head>
                @forelse ($agreements as $g)
                    <tr class="hover">
                        <td><a class="link" href="{{ route('dataprotection.gvv.show', $g) }}">{{ $g->title }}</a></td>
                        <td>{{ $g->partner?->name ?? '—' }}</td>
                        <td><x-status-badge tone="ghost" size="sm">{{ $g->status->label() }}</x-status-badge></td>
                        <td>{{ $g->essence_provided ? __('ja') : '—' }}</td>
                    </tr>
                @empty
                    <x-table.empty :colspan="4" :title="__('Keine GVV erfasst.')" />
                @endforelse
            </x-table>
        </x-card>

        <x-pagination :paginator="$agreements" />

        @can('create', \App\Models\Privacy\JointControllerAgreement::class)
            <x-card>
                <h2 class="font-['Space_Grotesk'] text-base font-semibold">{{ __('Neue GVV anlegen') }}</h2>
                <form method="post" action="{{ route('dataprotection.gvv.store') }}" enctype="multipart/form-data" class="space-y-2 mt-2">
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
                    <x-icon-btn icon="add" tone="primary" size="sm" type="submit" show-label>{{ __('GVV anlegen') }}</x-icon-btn>
                </form>
            </x-card>
        @endcan
    </x-index-page>
@endsection
