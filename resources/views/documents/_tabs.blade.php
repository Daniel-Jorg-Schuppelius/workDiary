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
    <x-tab-nav :items="[
        ['route' => 'documents.index', 'routeIs' => 'documents.*', 'icon' => 'folder_open', 'label' => __('document.title.index')],
        ['route' => 'form-submissions.index', 'routeIs' => 'form-submissions.*', 'icon' => 'edit_note', 'label' => __('form.title.submissions')],
    ]" />
@endif
