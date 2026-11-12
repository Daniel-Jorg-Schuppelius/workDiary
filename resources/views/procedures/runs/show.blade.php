@extends('layouts.app')
{{--
  Created on   : Mon Jun 22 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : show.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Mobile, Schritt-für-Schritt ausführbare Prozedurlauf-Ansicht (MVP-063):
  rendert bedingte Schritte, Warteschritte (MVP-064), Vier-Augen-Freigaben
  und Medien-Nachweise. Variablen: $run, $steps, $subject, $backUrl,
  $progressTotal, $progressDone, $canExecute, $canAbort, $missingRequired,
  $completionHints.
--}}
@php
    $version = $run->templateVersion;
    $tpl = $version?->template;
    $statusTone = match ($run->status->value) {
        'completed' => 'success',
        'aborted' => 'neutral',
        'blocked' => 'error',
        default => 'warning',
    };
    $runActive = $run->status->isActive();
@endphp
@section('title', ($tpl?->displayName() ?? __('procedure.print.title')) . ' — WorkDiary')
@section('nav-title', __('procedure.run.navTitle') . ' #' . $run->id)

@section('content')
    <x-page-shell>
        <x-page-toolbar :title="$tpl?->displayName() ?? '—'"
                        :badge="$run->status->label()"
                        :badge-tone="$statusTone"
                        :subtitle="__('Version :v', ['v' => $version?->version])"
                        :back="$backUrl" :back-label="__('procedure.action.back')">
            <x-slot:actions>
                <x-help-button topic="procedures.run" :label="__('Hilfe zu Prozedur')" />
                <x-icon-btn icon="print" tone="outline" size="sm" :href="route('procedure-runs.print', $run)"
                            target="_blank" :label="__('procedure.action.print')" />
            </x-slot:actions>
        </x-page-toolbar>

        <x-card padding="p-4 md:p-6" class="min-h-0 flex-1 overflow-auto">
            <div class="mx-auto max-w-2xl space-y-4">
                @if ($run->status === \App\Enums\Procedure\ProcedureRunStatus::Blocked && $run->blocked_reason !== null)
                    <div role="status" class="alert alert-error text-sm">{{ __('procedure.blocked_report.run_hint', ['reason' => __('procedure.blocked.' . $run->blocked_reason)]) }}</div>
                @endif
                {{-- Fortschritt --}}
                <div>
                    <div class="mb-1 flex items-center justify-between text-xs text-muted">
                        <span>{{ __('procedure.run.progress') }}</span>
                        <span>{{ $progressDone }} / {{ $progressTotal }}</span>
                    </div>
                    <progress class="progress progress-primary w-full" value="{{ $progressDone }}" max="{{ max(1, $progressTotal) }}"></progress>
                </div>

                {{-- Schritte --}}
                <ol class="space-y-3">
                    @foreach ($steps as $i => $step)
                        @php
                            /** @var \App\Models\Procedure\ProcedureStepRun $sr */
                            $sr = $step['stepRun'];
                            $def = $step['def'];
                            $isFinal = $sr->status->isFinal();
                            $stepTone = match ($sr->status->value) {
                                'done', 'n_a' => 'success',
                                'failed' => 'error',
                                'deviated' => 'warning',
                                'blocked' => 'neutral',
                                default => 'ghost',
                            };
                            $isWait = $def?->step_type === \App\Enums\Procedure\ProcedureStepType::Wait;
                            // Ohne Gegenzeichnung meldet blockReasonFor() den Schritt als gesperrt — die Vier-Augen-Leiste ersetzt dann den Sperrhinweis.
                            $awaitsSecondPerson = $step['blockReason'] === \App\Exceptions\ProcedureStepBlockedException::REASON_SECOND_PERSON_REQUIRED;
                            // Dasselbe am Backup-Schritt: die Backup-Karte ersetzt den Sperrhinweis.
                            $isBackup = $def?->step_type === \App\Enums\Procedure\ProcedureStepType::Backup;
                            $awaitsBackup = $step['blockReason'] === \App\Exceptions\ProcedureStepBlockedException::REASON_BACKUP_NOT_VERIFIED;
                        @endphp
                        <li class="rounded-box border {{ $step['isCurrent'] ? 'border-primary ring-1 ring-primary/30' : 'border-base-300' }} bg-base-200/30 p-4">
                            <div class="flex items-start justify-between gap-2">
                                <div>
                                    <div class="flex items-center gap-2">
                                        <x-status-badge>{{ $def?->sort_order ?? $i + 1 }}</x-status-badge>
                                        <span class="font-medium">{{ $def?->label ?? __('procedure.print.unknownStep') }}</span>
                                    </div>
                                    <div class="mt-1 flex flex-wrap items-center gap-1 text-xs text-muted">
                                        <span>{{ $def?->step_type?->label() ?? '—' }}</span>
                                        @if ($def?->required)<x-status-badge tone="plain" size="xs">{{ __('procedure.field.required') }}</x-status-badge>@endif
                                        @if ($def?->requires_second_person)<x-status-badge tone="plain" size="xs">{{ __('procedure.field.secondPerson') }}</x-status-badge>@endif
                                        @if (! $step['applicable'])<x-status-badge size="xs">{{ __('procedure.run.notApplicable') }}</x-status-badge>@endif
                                    </div>
                                    @if ($def?->description)
                                        <p class="mt-2 text-sm text-base-content/70">{{ $def->description }}</p>
                                    @endif
                                </div>
                                <x-status-badge :tone="$stepTone">{{ $sr->status->label() }}</x-status-badge>
                            </div>

                            {{-- Ergebnis abgeschlossener Schritte --}}
                            @if ($isFinal)
                                <div class="mt-2 text-xs text-muted">
                                    @if ($sr->executedBy){{ __('procedure.print.executedBy') }}: {{ $sr->executedBy->name }}@endif
                                    @if ($sr->executed_at) · {{ $sr->executed_at->fdatetime() }}@endif
                                    @if (data_get($sr->value_json, 'value')) · {{ __('procedure.run.value') }}: {{ data_get($sr->value_json, 'value') }}@endif
                                    @if ($sr->second_person_signed_at) · {{ __('procedure.field.secondPerson') }}: {{ $sr->secondPerson?->name }}@endif
                                </div>
                                @if ($sr->note)<p class="mt-1 whitespace-pre-wrap text-sm text-base-content/70">{{ $sr->note }}</p>@endif
                            @endif

                            @if ($step['requiresSecondPerson'] && ($awaitsSecondPerson || $sr->second_person_user_id !== null))
                                @include('procedures.runs._second_person', ['sr' => $sr, 'run' => $run, 'isFinal' => $isFinal, 'runActive' => $runActive])
                            @endif

                            @if ($isBackup && ($step['backupProof'] !== null || $awaitsBackup || $step['blockReason'] === null))
                                @include('procedures.runs._backup', ['sr' => $sr, 'run' => $run, 'proof' => $step['backupProof'], 'isFinal' => $isFinal, 'runActive' => $runActive])
                            @endif

                            @if ($step['deviation'] !== null)
                                @include('procedures.runs._deviation', ['deviation' => $step['deviation'], 'sr' => $sr, 'run' => $run, 'runActive' => $runActive, 'blocksCompletion' => $step['deviationBlocks']])
                            @endif

                            {{-- Sperrgrund --}}
                            @if (! $isFinal && $step['blockReason'] && ! $awaitsSecondPerson && ! $awaitsBackup)
                                <div class="mt-3 flex items-center gap-2 rounded-box bg-base-300/40 px-3 py-2 text-xs text-muted">
                                    <x-icon name="lock" class="text-muted" />
                                    {{ __('procedure.blocked.' . $step['blockReason']) }}
                                </div>
                            @endif

                            {{-- Aktionen für den aktuellen Schritt --}}
                            @if ($canExecute && $runActive && ! $isFinal && $step['blockReason'] === null && $step['isCurrent'])
                                @if ($isWait)
                                    @php $remaining = $step['waitRemaining']; @endphp
                                    @if ($sr->wait_until === null)
                                        <form method="POST" action="{{ route('procedure-runs.steps.wait.begin', [$run, $sr]) }}" class="mt-3 flex flex-wrap items-end gap-2">
                                            @csrf
                                            @if ((int) ($def->config['wait_seconds'] ?? 0) <= 0)
                                                <label class="form-control">
                                                    <span class="label-text text-xs">{{ __('procedure.run.waitSeconds') }}</span>
                                                    <input type="number" name="seconds" min="1" value="60" class="input input-bordered input-sm w-32" required>
                                                </label>
                                            @endif
                                            <x-button type="submit" icon="hourglass_top">
                                                {{ __('procedure.run.startWait') }}
                                            </x-button>
                                        </form>
                                    @elseif ($remaining > 0)
                                        <div class="mt-3 space-y-2">
                                            <div class="flex items-center gap-2 text-sm text-warning">
                                                <x-icon name="hourglass_bottom" />
                                                {{ __('procedure.run.waitRemaining', ['until' => $sr->wait_until->format('d.m.Y H:i')]) }}
                                            </div>
                                            <details class="text-xs">
                                                <summary class="cursor-pointer text-muted">{{ __('procedure.run.overrideWait') }}</summary>
                                                <form method="POST" action="{{ route('procedure-runs.steps.wait.continue', [$run, $sr]) }}" class="mt-2 space-y-2">
                                                    @csrf
                                                    <textarea aria-label="{{ __('procedure.run.overrideReason') }}" name="reason" rows="2" minlength="5" required
                                                              class="textarea textarea-bordered textarea-sm w-full"
                                                              placeholder="{{ __('procedure.run.overrideReason') }}"></textarea>
                                                    <x-button type="submit" tone="warning" size="xs">{{ __('procedure.run.overrideWaitConfirm') }}</x-button>
                                                </form>
                                            </details>
                                        </div>
                                    @else
                                        <form method="POST" action="{{ route('procedure-runs.steps.wait.continue', [$run, $sr]) }}" class="mt-3">
                                            @csrf
                                            <x-button type="submit" icon="play_arrow">
                                                {{ __('procedure.run.continueWait') }}
                                            </x-button>
                                        </form>
                                    @endif
                                @else
                                    <form method="POST" action="{{ route('procedure-runs.steps.execute', [$run, $sr]) }}"
                                          enctype="multipart/form-data" class="mt-3 space-y-2">
                                        @csrf
                                        @php $stepField = $def !== null ? app(\App\Services\Procedure\Fields\ProcedureStepFields::class)->definition($def) : null; @endphp
                                        @if ($stepField !== null)
                                            <x-field-input :field="$stepField" prefix="" :wide="false" />
                                        @endif
                                        @if (in_array($def?->step_type?->value, ['photo', 'file', 'signature'], true) || $def?->requires_proof_type)
                                            <input type="file" name="proof" class="file-input file-input-bordered file-input-sm w-full">
                                        @endif
                                        <textarea aria-label="{{ __('procedure.run.notePlaceholder') }}" name="note" rows="2" class="textarea textarea-bordered textarea-sm w-full"
                                                  placeholder="{{ __('procedure.run.notePlaceholder') }}"></textarea>
                                        <div class="flex flex-wrap gap-2">
                                            <x-button type="submit" icon="check" name="status" value="done">
                                                {{ __('procedure.run.markDone') }}
                                            </x-button>
                                            @unless ($def?->required)
                                                <x-button type="submit" tone="ghost" name="status" value="n_a">{{ __('procedure.run.markNa') }}</x-button>
                                            @endunless
                                            <x-button type="submit" tone="error" class="btn-outline" name="status" value="failed">{{ __('procedure.run.markFailed') }}</x-button>
                                        </div>
                                    </form>

                                    @include('procedures.runs._deviation_form', ['run' => $run, 'sr' => $sr])
                                @endif
                            @elseif ($canExecute && $runActive && ! $isFinal && $awaitsBackup)
                                {{-- Ohne Nachweis bleibt nur die begründete Abweichung („Backup nicht möglich“). --}}
                                @include('procedures.runs._deviation_form', ['run' => $run, 'sr' => $sr])
                            @elseif ($canExecute && $runActive && ! $isFinal && ! $step['applicable'] && $step['blockReason'] === null)
                                {{-- Nicht zutreffender bedingter Schritt: schnelle N/A-Erledigung --}}
                                <form method="POST" action="{{ route('procedure-runs.steps.execute', [$run, $sr]) }}" class="mt-3">
                                    @csrf
                                    <x-button type="submit" tone="ghost" size="xs" name="status" value="n_a">{{ __('procedure.run.markNa') }}</x-button>
                                </form>
                            @endif
                        </li>
                    @endforeach
                </ol>

                {{-- Lauf-Aktionen --}}
                @if ($runActive && $canExecute)
                    <div class="flex flex-wrap items-center justify-between gap-2 border-t border-base-300 pt-4">
                        <form method="POST" action="{{ route('procedure-runs.complete', $run) }}">
                            @csrf
                            <x-button type="submit" tone="success" icon="task_alt" :disabled="! empty($missingRequired) || $completionHints !== []">
                                {{ __('procedure.run.complete') }}
                            </x-button>
                        </form>
                        @if ($canAbort)
                            <details class="dropdown dropdown-end">
                                <summary class="btn btn-sm btn-ghost text-error">{{ __('procedure.run.abort') }}</summary>
                                <form method="POST" action="{{ route('procedure-runs.abort', $run) }}"
                                      class="dropdown-content z-10 w-72 space-y-2 rounded-box border border-base-300 bg-base-100 p-3 shadow">
                                    @csrf
                                    <textarea aria-label="{{ __('procedure.print.abortReason') }}" name="reason" rows="2" class="textarea textarea-bordered textarea-sm w-full"
                                              placeholder="{{ __('procedure.print.abortReason') }}"></textarea>
                                    <x-button type="submit" tone="error" size="xs">{{ __('procedure.run.abortConfirm') }}</x-button>
                                </form>
                            </details>
                        @endif
                    </div>
                    @if (! empty($missingRequired))
                        <p class="text-xs text-muted">{{ __('procedure.run.completeHint') }}</p>
                    @endif
                    @foreach ($completionHints as $hint)
                        <p class="text-xs text-muted">{{ $hint }}</p>
                    @endforeach
                @endif
            </div>
        </x-card>
    </x-page-shell>
@endsection
