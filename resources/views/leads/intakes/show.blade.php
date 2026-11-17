{{--
  Created on   : Thu Nov 12 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : show.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}

{{--
  Aufnahmeakte am Lead (Feature 200, MVP-1641–1644). Variablen: $lead, $intake,
  $schema/$values (nur, was die Person sehen darf), $hiddenAnswered (Art → Anzahl),
  $hiddenCount, $documents (Feldschlüssel → Anhänge), $documentFields, $versions,
  $canEdit, $canDecide, $canRefresh, $canArchive, $autosave, $acceptanceErrors.
  Der Bogen nutzt <x-field-form> aus Feature 203 (Speichern je Feld,
  Gesprächsmodus); nach der Entscheidung bleibt er nur lesbar.
--}}

@extends('layouts.app')

@section('title', __('lead_intake.title_for', ['name' => $lead->displayName()]))
@section('nav-title', __('lead_intake.title'))

@php
    /** @var \App\Models\Sales\LeadIntake $intake */
    use App\Enums\Sales\LeadIntakeStage;
    $me = auth()->user();
    $readOnlySections = $schema->sections();
@endphp

@section('content')
<x-page-shell>
    <x-slot:toolbar>
        <x-page-toolbar :back="route('leads.show', $lead)" :back-label="__('lead_intake.back_to_lead')">
            <div class="flex min-w-0 flex-wrap items-center gap-2">
                <span class="truncate font-medium">{{ $lead->displayName() }}</span>
                <x-status-badge :tone="$intake->stage->tone()" size="sm">{{ $intake->stage->label() }}</x-status-badge>
                @if ($intake->revision > 1)
                    <x-status-badge tone="ghost" size="sm">{{ __('lead_intake.revision', ['revision' => $intake->revision]) }}</x-status-badge>
                @endif
                @if (! $intake->isLatest())
                    <x-status-badge tone="warning" size="sm">{{ __('lead_intake.superseded') }}</x-status-badge>
                @endif
            </div>
            <x-slot:actions>
                @if ($canDecide && $intake->acceptsFieldAutosave())
                    <x-icon-btn icon="gavel" tone="primary" size="sm" data-entry-modal-trigger
                                :href="route('leads.intakes.decision', [$lead, $intake]) . '?dialog=1'" show-label>{{ __('lead_intake.action.decide') }}</x-icon-btn>
                @endif
                @if ($canRefresh)
                    <x-action-form :action="route('leads.intakes.refresh', [$lead, $intake])" :confirm="__('lead_intake.confirm.refresh')" confirm-icon="history">
                        <x-icon-btn icon="history" size="sm" type="submit" show-label>{{ __('lead_intake.action.refresh') }}</x-icon-btn>
                    </x-action-form>
                @endif
                <x-icon-btn icon="picture_as_pdf" tone="ghost" size="sm" :href="route('leads.intakes.pdf', [$lead, $intake])" show-label>{{ __('lead_intake.action.pdf') }}</x-icon-btn>
                @if ($canArchive)
                    <x-action-form :action="route('leads.intakes.archive', [$lead, $intake])" :confirm="__('lead_intake.confirm.archive')" confirm-icon="folder">
                        <x-icon-btn icon="folder" tone="ghost" size="sm" type="submit" show-label>{{ __('lead_intake.action.archive') }}</x-icon-btn>
                    </x-action-form>
                @endif
            </x-slot:actions>
        </x-page-toolbar>
    </x-slot:toolbar>

    <div class="grid gap-4 lg:grid-cols-3">
        <div class="space-y-4 lg:col-span-2">
            {{-- Stufen: Anfrage → Gespräch → Unterlagen → Entscheidung → Einrichtung --}}
            <x-card as="section" :title="__('lead_intake.stages')" icon="linear_scale">
                <ol class="flex flex-wrap gap-2 text-sm" aria-label="{{ __('lead_intake.stages') }}">
                    @foreach (LeadIntakeStage::pipeline() as $step)
                        <li>
                            <x-status-badge size="sm" :tone="$step === $intake->stage ? $step->tone() : 'ghost'">
                                @if ($step === $intake->stage)<span class="sr-only">{{ __('lead_intake.current_stage') }}:</span>@endif
                                {{ $loop->iteration }}. {{ $step->label() }}
                            </x-status-badge>
                        </li>
                    @endforeach
                </ol>
                @if ($canEdit)
                    <div class="mt-3 flex flex-wrap gap-2">
                        @foreach ($intake->stage->allowedTransitions() as $next)
                            @continue(! $next->isEditable())
                            <x-action-form :action="route('leads.intakes.stage', [$lead, $intake])">
                                <input type="hidden" name="stage" value="{{ $next->value }}">
                                <x-icon-btn icon="arrow_forward" size="sm" type="submit" show-label>{{ __('lead_intake.action.move_to', ['stage' => $next->label()]) }}</x-icon-btn>
                            </x-action-form>
                        @endforeach
                    </div>
                @endif
            </x-card>

            @if ($hiddenCount > 0)
                <div role="note" class="alert alert-info text-sm">
                    <x-icon name="lock" />
                    <span>
                        {{ __('lead_intake.hint.restricted', ['count' => $hiddenCount]) }}
                        @foreach ($hiddenAnswered as $kind => $count)
                            {{ __('lead_intake.hint.restricted_answered', ['kind' => \App\Enums\Sales\IntakeSensitivity::from($kind)->label(), 'count' => $count]) }}
                        @endforeach
                    </span>
                </div>
            @endif

            {{-- Gesprächsbogen --}}
            @if ($canEdit)
                <form method="POST" action="{{ route('leads.intakes.save', [$lead, $intake]) }}" class="space-y-3" data-intake-form>
                    @csrf
                    <input type="hidden" name="draft" value="{{ $intake->sqid }}">
                    <input type="hidden" name="draft_version" value="{{ $intake->version }}">
                    <x-field-form :schema="$schema" :values="$values" :title="__('lead_intake.form.section_base')" notes talk :autosave="$autosave" :heading-level="2" />
                    <div class="flex justify-end">
                        <x-button type="submit" tone="primary" icon="save">{{ __('lead_intake.action.save') }}</x-button>
                    </div>
                </form>
            @else
                @foreach ($readOnlySections as $section)
                    @continue($section['fields'] === [])
                    <x-card as="section" :title="$section['field']?->label ?? __('lead_intake.form.section_base')">
                        <x-detail-grid layout="split" cols="2" small-labels>
                            @foreach ($section['fields'] as $field)
                                <x-detail-grid.row :label="$field->label"><x-field-display :field="$field" :values="$values" /></x-detail-grid.row>
                            @endforeach
                        </x-detail-grid>
                    </x-card>
                @endforeach
            @endif

            {{-- Unterlagen mit Anhängen; eng sichtbare nur mit eigenem Recht --}}
            @if ($documentFields !== [])
                <x-card as="section" id="intake-documents" :title="__('lead_intake.documents.title')" icon="folder_open" padding="p-0">
                    <ul class="divide-y divide-base-300">
                        @foreach ($documentFields as $field)
                            <li class="space-y-2 px-4 py-3 text-sm" data-intake-document="{{ $field->key }}">
                                <div class="flex flex-wrap items-center justify-between gap-2">
                                    <span class="font-medium">
                                        <x-icon :name="$values->isAnswered($field) ? 'check_box' : 'check_box_outline_blank'" class="align-middle" />
                                        {{ $field->label }}
                                    </span>
                                    @if (isset($intake->sensitiveMap()[$field->key]))
                                        <x-status-badge size="xs" tone="warning">{{ $intake->sensitiveMap()[$field->key]->label() }}</x-status-badge>
                                    @endif
                                </div>
                                @foreach ($documents[$field->key] ?? [] as $attachment)
                                    <div class="text-xs">
                                        <a class="link link-hover" href="{{ \App\Http\Controllers\Attachments\AttachmentController::downloadUrl($attachment) }}">{{ $attachment->original_name }}</a>
                                        <span class="text-muted">· {{ $attachment->created_at?->fdate() }}</span>
                                    </div>
                                @endforeach
                                @if ($canEdit)
                                    <form method="POST" action="{{ route('leads.intakes.document', [$lead, $intake]) }}" enctype="multipart/form-data" class="flex flex-wrap items-center gap-2">
                                        @csrf
                                        <input type="hidden" name="document" value="{{ $field->key }}">
                                        <label class="sr-only" for="intake-doc-{{ $field->key }}">{{ __('lead_intake.documents.upload_for', ['document' => $field->label]) }}</label>
                                        <input type="file" name="file" id="intake-doc-{{ $field->key }}" class="file-input file-input-sm file-input-bordered" required>
                                        <x-button type="submit" size="sm" icon="upload">{{ __('lead_intake.documents.upload') }}</x-button>
                                    </form>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                </x-card>
            @endif
        </div>

        <div class="space-y-4">
            {{-- Entscheidung --}}
            <x-card as="section" :title="__('lead_intake.decision.title')" icon="gavel">
                @if ($intake->decision !== null)
                    <x-detail-grid small-labels>
                        <x-detail-grid.row :label="__('lead_intake.field.decision')">
                            <x-status-badge size="sm" :tone="$intake->decision->tone()">{{ $intake->decision->label() }}</x-status-badge>
                        </x-detail-grid.row>
                        <x-detail-grid.row :label="__('lead_intake.field.decision_reason')"><span class="whitespace-pre-line">{{ $intake->decision_reason }}</span></x-detail-grid.row>
                        <x-detail-grid.row :label="__('lead_intake.field.decided_by')">{{ $intake->decider?->name ?? '—' }} · {{ $intake->decided_at?->fdatetime() ?? '—' }}</x-detail-grid.row>
                        @if ($intake->second_reviewed_at !== null)
                            <x-detail-grid.row :label="__('lead_intake.field.second_review')">{{ $intake->secondReviewer?->name ?? '—' }} · {{ $intake->second_reviewed_at->fdatetime() }}</x-detail-grid.row>
                        @endif
                        @if ($intake->protocol !== null)
                            <x-detail-grid.row :label="__('lead_intake.field.protocol')">
                                <a class="link link-hover" href="{{ route('protocols.show', $intake->protocol) }}">{{ $intake->protocol->title }}</a>
                            </x-detail-grid.row>
                        @endif
                    </x-detail-grid>
                    @if ($intake->awaitsSecondReview())
                        <div class="mt-3 space-y-2">
                            <p class="text-sm text-warning">{{ __('lead_intake.decision.second_review_pending') }}</p>
                            @if ($canDecide && (int) $intake->decider_user_id !== (int) $me?->id)
                                <x-action-form :action="route('leads.intakes.second-review', [$lead, $intake])" :confirm="__('lead_intake.confirm.second_review')" confirm-icon="verified">
                                    <input type="hidden" name="action" value="confirm">
                                    <x-icon-btn icon="verified" tone="primary" size="sm" type="submit" show-label>{{ __('lead_intake.action.confirm_second_review') }}</x-icon-btn>
                                </x-action-form>
                                <form method="POST" action="{{ route('leads.intakes.second-review', [$lead, $intake]) }}" class="space-y-2">
                                    @csrf
                                    <input type="hidden" name="action" value="return">
                                    <x-textarea-field name="reason" id="intake-return-reason" :label="__('lead_intake.decision.return_reason')" required />
                                    <x-button type="submit" size="sm" icon="undo">{{ __('lead_intake.action.return_second_review') }}</x-button>
                                </form>
                            @endif
                        </div>
                    @endif
                @else
                    <p class="text-sm text-muted">{{ __('lead_intake.decision.open') }}</p>
                    @if ($acceptanceErrors !== [])
                        <p class="mt-2 text-sm font-medium">{{ __('lead_intake.decision.missing', ['count' => count($acceptanceErrors)]) }}</p>
                        <ul class="mt-1 list-disc ps-5 text-xs text-muted">
                            @foreach (array_slice($acceptanceErrors, 0, 8, true) as $message)
                                <li>{{ $message }}</li>
                            @endforeach
                        </ul>
                    @endif
                    @if ($intake->requiresDecision())
                        <p class="mt-2 text-xs text-muted">{{ __('lead_intake.decision.blocks_conversion') }}</p>
                    @endif
                @endif
            </x-card>

            {{-- Pakete, Fristen --}}
            <x-card as="section" :title="__('lead_intake.packages.title')" icon="inventory_2">
                <x-detail-grid small-labels>
                    <x-detail-grid.row :label="__('lead_intake.field.packages')">
                        {{ implode(', ', array_map(static fn ($p): string => $p->label, $intake->packageSnapshots())) ?: __('lead_intake.packages.none') }}
                    </x-detail-grid.row>
                    <x-detail-grid.row :label="__('lead_intake.field.refresh_due_on')">{{ $intake->refresh_due_on?->fdate() ?? '—' }}</x-detail-grid.row>
                    <x-detail-grid.row :label="__('lead_intake.field.retention_until')">
                        {{ $intake->retention_until?->fdate() ?? ($intake->retains() ? __('lead_intake.retention.open') : '—') }}
                    </x-detail-grid.row>
                    @if ($intake->customer !== null)
                        <x-detail-grid.row :label="__('lead_intake.field.customer')">
                            <a class="link link-hover" href="{{ route('customers.show', $intake->customer) }}">{{ $intake->customer->name }}</a>
                        </x-detail-grid.row>
                    @endif
                </x-detail-grid>
            </x-card>

            @if ($versions->count() > 1)
                <x-card as="section" :title="__('lead_intake.versions.title')" icon="history" padding="p-0">
                    <ul class="divide-y divide-base-300 text-sm">
                        @foreach ($versions as $version)
                            <li class="flex items-center justify-between gap-2 px-4 py-2">
                                <a class="link link-hover" href="{{ route('leads.intakes.show', [$lead, $version]) }}">{{ __('lead_intake.revision', ['revision' => $version->revision]) }}</a>
                                <span class="text-xs text-muted">{{ $version->stage->label() }} · {{ $version->decided_at?->fdate() ?? $version->created_at?->fdate() }}</span>
                            </li>
                        @endforeach
                    </ul>
                </x-card>
            @endif

            <x-activity-feed :subject="$intake" />
        </div>
    </div>
</x-page-shell>
@endsection
