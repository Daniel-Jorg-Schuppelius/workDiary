{{--
  Created on   : Wed Jun 10 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _panel.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Karte „Formulare" (Feature 032) für Detailseiten (Auftrag etc.):
  ausgefüllte Formulare des Bezugs + „Formular ausfüllen"-Auswahl
  aktiver Vorlagen (Muster: documents._panel / knowledge._context_card).
  Erwartet: $subject (Model), $subjectKind ('diary'|'customer'|'asset'|'project')
--}}
@php
    $canViewForms = \Illuminate\Support\Facades\Gate::allows('viewAny', \App\Models\FormSubmission::class)
        && app(\App\Services\Licensing\FeatureFlagResolver::class)->isEnabled('module.forms');
@endphp

@if ($canViewForms)
@php
    /** @var \App\Models\User $panelUser */
    $panelUser = auth()->user();
    $panelSubjectSqid = \App\Support\Sqid::encode(get_class($subject), (int) $subject->getKey());
    $panelSubmissionsQuery = \App\Models\FormSubmission::query()
        ->where('subject_type', $subject->getMorphClass())
        ->where('subject_id', $subject->getKey())
        ->with(['template', 'submitter'])
        ->orderByDesc('submitted_at');
    // user/aussendienst sehen nur die EIGENEN Submissions (Policy-Spiegel).
    if (! $panelUser->isAdmin() && ! $panelUser->can(\App\Enums\User\Permission::FormTemplateViewAny->value)) {
        $panelSubmissionsQuery->where('submitted_by_user_id', $panelUser->id);
    }
    $panelSubmissions = $panelSubmissionsQuery->get();
    // Zuordnungsfilter (Feature 032 MVP; Vollaudit 2026-07, M11): am Bezug nur
    // Vorlagen anbieten, deren Ziel (Auftragstyp/Kunde) zum Subject passt.
    $panelEntryTypeId = $subject instanceof \App\Models\DiaryEntry ? ($subject->entry_type_id !== null ? (int) $subject->entry_type_id : null) : null;
    $panelCustomerId = match (true) {
        $subject instanceof \App\Models\Customer => (int) $subject->getKey(),
        $subject instanceof \App\Models\DiaryEntry => $subject->customer_id !== null ? (int) $subject->customer_id : null,
        $subject instanceof \App\Models\Project => $subject->customer_id !== null ? (int) $subject->customer_id : null,
        default => null,
    };
    $panelActiveTemplates = \Illuminate\Support\Facades\Gate::allows('create', \App\Models\FormSubmission::class)
        ? \App\Models\FormTemplate::query()->active()->orderBy('name')->get()
            ->filter(fn(\App\Models\FormTemplate $t): bool => $t->matchesSubject($panelEntryTypeId, $panelCustomerId))
            ->values()
        : collect();
@endphp

<x-card as="section" id="forms">
    <div class="mb-4 flex flex-wrap items-center justify-between gap-2">
        <h2 class="flex items-center gap-2 font-['Space_Grotesk'] text-base font-semibold text-base-content">
            <x-icon name="edit_note" class="text-muted" /> {{ __('form.title.panel') }}
            <span class="font-normal text-muted">({{ $panelSubmissions->count() }})</span>
        </h2>
        @if ($panelActiveTemplates->isNotEmpty())
            <div class="dropdown dropdown-end">
                <x-icon-btn icon="edit_note" tone="primary" size="sm" type="button" tabindex="0" show-label>
                    {{ __('form.action.fill') }}
                </x-icon-btn>
                <ul tabindex="0" class="dropdown-content menu z-30 mt-1 w-64 rounded-box border border-base-300 bg-base-100 p-2 shadow">
                    @foreach ($panelActiveTemplates as $panelTemplate)
                        <li>
                            <a data-entry-modal-trigger
                               href="{{ route('form-submissions.create', ['template' => $panelTemplate->sqid, 'subject_kind' => $subjectKind, 'subject_id' => $panelSubjectSqid]) }}">
                                {{ $panelTemplate->name }}
                            </a>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif
    </div>

    @if ($panelSubmissions->isEmpty())
        <x-empty-state compact icon="edit_note"
                       :title="__('form.title.panel')"
                       :message="__('form.empty_panel')" />
    @else
        <ul class="divide-y divide-base-300">
            @foreach ($panelSubmissions as $panelSubmission)
                <li id="form-submission-{{ $panelSubmission->id }}" class="flex flex-wrap items-center justify-between gap-2 py-2 text-sm">
                    <div class="min-w-0">
                        <a href="{{ route('form-submissions.show', $panelSubmission) }}" class="flex items-center gap-2 font-medium link-hover">
                            <x-icon name="edit_note" class="text-muted" />
                            {{ optional($panelSubmission->template)->name ?? '—' }}
                        </a>
                        <span class="block text-xs text-muted">
                            {{ optional($panelSubmission->submitter)->name ?? '—' }}
                            · {{ $panelSubmission->submitted_at?->fdatetime() ?? '—' }}
                        </span>
                    </div>
                    <x-icon-btn icon="visibility" tone="outline" size="xs"
                                :href="route('form-submissions.show', $panelSubmission)"
                                :label="__('form.action.show')" />
                </li>
            @endforeach
        </ul>
    @endif
</x-card>
@endif
