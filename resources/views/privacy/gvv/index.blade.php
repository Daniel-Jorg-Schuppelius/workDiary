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
            <div>
                <x-icon-btn icon="add" tone="primary" size="sm"
                            onclick="document.getElementById('dlg-gvv').showModal()" show-label>{{ __('Neue GVV anlegen') }}</x-icon-btn>
            </div>
            <x-modal :embedded="false" id="dlg-gvv" :title="__('Neue GVV anlegen')"
                     icon="diversity_3" tone="primary"
                     :action="route('dataprotection.gvv.store')" method="POST"
                     enctype="multipart/form-data" :submit-label="__('GVV anlegen')">
                <x-form-group :legend="__('Eckdaten')" icon="diversity_3" tone="primary" cols="2">
                    <x-input-field name="partner_id" :label="__('Partner (Dienstleister)')" required>
                        <select id="partner_id" name="partner_id" class="select select-bordered w-full" required>
                            <option value="">{{ __('Partner (Dienstleister) …') }}</option>
                            @foreach ($partners as $p)<option value="{{ $p->id }}">{{ $p->name }}</option>@endforeach
                        </select>
                    </x-input-field>
                    <x-input-field name="title" :label="__('Titel')" required />
                    <x-input-field type="date" name="valid_from" :label="__('Gültig ab')" />
                    <x-input-field name="contact_point" :label="__('Gemeinsame Anlaufstelle')" />
                </x-form-group>
                <x-form-group :legend="__('Zuständigkeitsmatrix')" icon="checklist" tone="ghost" cols="1">
                    @foreach ($matrixKeys as $k)
                        <div class="flex items-center justify-between gap-2">
                            <span class="text-sm flex-1">{{ $matrixLabels[$k] ?? $k }}</span>
                            <select name="responsibilities[{{ $k }}]" class="select select-bordered">
                                @foreach ($roleOptions as $v => $l)<option value="{{ $v }}">{{ $l }}</option>@endforeach
                            </select>
                        </div>
                    @endforeach
                    <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="essence_provided" value="1" class="checkbox checkbox-sm"> {{ __('Wesentliches der GVV den Betroffenen bereitgestellt') }}</label>
                </x-form-group>
                <x-form-group :legend="__('Dokument')" icon="upload_file" tone="ghost" cols="1">
                    <x-input-field type="file" name="document" :label="__('Vertragsdokument')" accept=".pdf,.doc,.docx" />
                </x-form-group>
            </x-modal>
        @endcan
    </x-index-page>
@endsection
