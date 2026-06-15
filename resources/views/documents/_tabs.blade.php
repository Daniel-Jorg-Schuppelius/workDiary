{{--
    Gemeinsame Tab-Leiste „Dokumente ↔ Formulare" (zusammengelegt). Aktiver Tab
    über routeIs. Jeder Tab erscheint nur, wenn Recht UND Modul vorhanden sind
    (Document: module.documents/document.viewAny · FormSubmission: module.forms/
    formSubmission.viewAny) — analog zur Sidebar-Gating-Logik.
--}}
@php
    $ff = app(\App\Services\Licensing\FeatureFlagResolver::class);
    $showDocs = $ff->isEnabled('module.documents') && \Illuminate\Support\Facades\Gate::allows('viewAny', \App\Models\Document::class);
    $showForms = $ff->isEnabled('module.forms') && \Illuminate\Support\Facades\Gate::allows('viewAny', \App\Models\FormSubmission::class);
@endphp
@if ($showDocs && $showForms)
    <div role="tablist" class="tabs tabs-box w-full">
        <a role="tab"
           href="{{ route('documents.index') }}"
           @class(['tab gap-1', 'tab-active' => request()->routeIs('documents.*')])
           @if (request()->routeIs('documents.*')) aria-current="page" @endif>
            <span class="material-symbols-outlined text-base" aria-hidden="true">folder_open</span>
            {{ __('document.title.index') }}
        </a>
        <a role="tab"
           href="{{ route('form-submissions.index') }}"
           @class(['tab gap-1', 'tab-active' => request()->routeIs('form-submissions.*')])
           @if (request()->routeIs('form-submissions.*')) aria-current="page" @endif>
            <span class="material-symbols-outlined text-base" aria-hidden="true">edit_note</span>
            {{ __('form.title.submissions') }}
        </a>
    </div>
@endif
