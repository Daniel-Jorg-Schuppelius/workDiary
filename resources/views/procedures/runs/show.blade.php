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
  $progressTotal, $progressDone, $canExecute, $canAbort, $missingRequired.
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
@section('title', ($tpl?->name ?? __('procedure.print.title')) . ' — WorkDiary')
@section('nav-title', __('procedure.run.navTitle') . ' #' . $run->id)

@section('content')
    <x-page-shell>
        <div class="flex flex-wrap items-center justify-between gap-2">
            <div class="flex items-center gap-2">
                <x-icon name="rule" class="text-base-content/50" />
                <h1 class="text-lg font-semibold"><x-term glossary="prozedur">{{ $tpl?->name ?? '—' }}</x-term></h1>
                <span class="text-xs text-base-content/50">v{{ $version?->version }}</span>
                <x-status-badge :tone="$statusTone">{{ $run->status->label() }}</x-status-badge>
            </div>
            <div class="flex items-center gap-2">
                <x-help-button topic="procedures.run" :label="__('Hilfe zu Prozedur')" />
                <x-icon-btn icon="print" tone="outline" size="sm" :href="route('procedure-runs.print', $run)"
                            target="_blank" :label="__('procedure.action.print')" />
                <x-icon-btn icon="arrow_back" tone="ghost" size="sm" :href="$backUrl" :label="__('procedure.action.back')" />
            </div>
        </div>

        <div class="min-h-0 flex-1 overflow-auto rounded-box border border-base-300 bg-base-100 p-4 shadow-xs md:p-6">
            <div class="mx-auto max-w-2xl space-y-4">
                {{-- Fortschritt --}}
                <div>
                    <div class="mb-1 flex items-center justify-between text-xs text-base-content/60">
                        <span>{{ __('procedure.run.progress') }}</span>
                        <span>{{ $progressDone }} / {{ $progressTotal }}</span>
                    </div>
                    <progress class="progress progress-primary w-full" value="{{ $progressDone }}" max="{{ max(1, $progressTotal) }}"></progress>
                </div>

                {{-- Schritte --}}
                <ol class="space-y-3">
                    @foreach ($steps as $i => $step)
                        @php
                            /** @var \App\Models\ProcedureStepRun $sr */
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
                            $needsSecondPerson = $def?->requires_second_person
                                && ($sr->second_person_user_id === null || $sr->second_person_signed_at === null);
                        @endphp
                        <li class="rounded-box border {{ $step['isCurrent'] ? 'border-primary ring-1 ring-primary/30' : 'border-base-300' }} bg-base-200/30 p-4">
                            <div class="flex items-start justify-between gap-2">
                                <div>
                                    <div class="flex items-center gap-2">
                                        <span class="badge badge-sm badge-ghost">{{ $def?->sort_order ?? $i + 1 }}</span>
                                        <span class="font-medium">{{ $def?->label ?? __('procedure.print.unknownStep') }}</span>
                                    </div>
                                    <div class="mt-1 flex flex-wrap items-center gap-1 text-xs text-base-content/50">
                                        <span>{{ $def?->step_type?->label() ?? '—' }}</span>
                                        @if ($def?->required)<span class="badge badge-xs">{{ __('procedure.field.required') }}</span>@endif
                                        @if ($def?->requires_second_person)<span class="badge badge-xs">{{ __('procedure.field.secondPerson') }}</span>@endif
                                        @if (! $step['applicable'])<span class="badge badge-xs badge-ghost">{{ __('procedure.run.notApplicable') }}</span>@endif
                                    </div>
                                    @if ($def?->description)
                                        <p class="mt-2 text-sm text-base-content/70">{{ $def->description }}</p>
                                    @endif
                                </div>
                                <x-status-badge :tone="$stepTone">{{ $sr->status->label() }}</x-status-badge>
                            </div>

                            {{-- Ergebnis abgeschlossener Schritte --}}
                            @if ($isFinal)
                                <div class="mt-2 text-xs text-base-content/60">
                                    @if ($sr->executedBy){{ __('procedure.print.executedBy') }}: {{ $sr->executedBy->name }}@endif
                                    @if ($sr->executed_at) · {{ $sr->executed_at->format('d.m.Y H:i') }}@endif
                                    @if (data_get($sr->value_json, 'value')) · {{ __('procedure.run.value') }}: {{ data_get($sr->value_json, 'value') }}@endif
                                    @if ($sr->second_person_signed_at) · {{ __('procedure.field.secondPerson') }}: {{ $sr->secondPerson?->name }}@endif
                                </div>
                                @if ($sr->note)<p class="mt-1 whitespace-pre-wrap text-sm text-base-content/70">{{ $sr->note }}</p>@endif
                            @endif

                            {{-- Sperrgrund --}}
                            @if (! $isFinal && $step['blockReason'])
                                <div class="mt-3 flex items-center gap-2 rounded-box bg-base-300/40 px-3 py-2 text-xs text-base-content/60">
                                    <x-icon name="lock" class="text-base-content/40" />
                                    {{ __('procedure.blocked.' . $step['blockReason']) }}
                                </div>
                            @endif

                            {{-- Aktionen für den aktuellen Schritt --}}
                            @if ($canExecute && $runActive && ! $isFinal && $step['blockReason'] === null && $step['isCurrent'])
                                @if ($needsSecondPerson)
                                    <form method="POST" action="{{ route('procedure-runs.steps.second-person', [$run, $sr]) }}" class="mt-3">
                                        @csrf
                                        <button type="submit" class="btn btn-sm btn-secondary">
                                            <x-icon name="how_to_reg" /> {{ __('procedure.run.signSecondPerson') }}
                                        </button>
                                    </form>
                                @elseif ($isWait)
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
                                            <button type="submit" class="btn btn-sm btn-primary">
                                                <x-icon name="hourglass_top" /> {{ __('procedure.run.startWait') }}
                                            </button>
                                        </form>
                                    @elseif ($remaining > 0)
                                        <div class="mt-3 space-y-2">
                                            <div class="flex items-center gap-2 text-sm text-warning">
                                                <x-icon name="hourglass_bottom" />
                                                {{ __('procedure.run.waitRemaining', ['until' => $sr->wait_until->format('d.m.Y H:i')]) }}
                                            </div>
                                            <details class="text-xs">
                                                <summary class="cursor-pointer text-base-content/50">{{ __('procedure.run.overrideWait') }}</summary>
                                                <form method="POST" action="{{ route('procedure-runs.steps.wait.continue', [$run, $sr]) }}" class="mt-2 space-y-2">
                                                    @csrf
                                                    <textarea name="reason" rows="2" minlength="5" required
                                                              class="textarea textarea-bordered textarea-sm w-full"
                                                              placeholder="{{ __('procedure.run.overrideReason') }}"></textarea>
                                                    <button type="submit" class="btn btn-xs btn-warning">{{ __('procedure.run.overrideWaitConfirm') }}</button>
                                                </form>
                                            </details>
                                        </div>
                                    @else
                                        <form method="POST" action="{{ route('procedure-runs.steps.wait.continue', [$run, $sr]) }}" class="mt-3">
                                            @csrf
                                            <button type="submit" class="btn btn-sm btn-primary">
                                                <x-icon name="play_arrow" /> {{ __('procedure.run.continueWait') }}
                                            </button>
                                        </form>
                                    @endif
                                @else
                                    <form method="POST" action="{{ route('procedure-runs.steps.execute', [$run, $sr]) }}"
                                          enctype="multipart/form-data" class="mt-3 space-y-2">
                                        @csrf
                                        @if (in_array($def?->step_type?->value, ['text', 'number', 'choice', 'messreihe'], true))
                                            <input type="{{ $def?->step_type?->value === 'number' ? 'number' : 'text' }}" name="value" step="any"
                                                   class="input input-bordered input-sm w-full"
                                                   placeholder="{{ __('procedure.run.value') }}">
                                        @endif
                                        @if (in_array($def?->step_type?->value, ['photo', 'file', 'signature'], true) || $def?->requires_proof_type)
                                            <input type="file" name="proof" class="file-input file-input-bordered file-input-sm w-full">
                                        @endif
                                        <textarea name="note" rows="2" class="textarea textarea-bordered textarea-sm w-full"
                                                  placeholder="{{ __('procedure.run.notePlaceholder') }}"></textarea>
                                        <div class="flex flex-wrap gap-2">
                                            <button type="submit" name="status" value="done" class="btn btn-sm btn-primary">
                                                <x-icon name="check" /> {{ __('procedure.run.markDone') }}
                                            </button>
                                            @unless ($def?->required)
                                                <button type="submit" name="status" value="n_a" class="btn btn-sm btn-ghost">{{ __('procedure.run.markNa') }}</button>
                                            @endunless
                                            <button type="submit" name="status" value="failed" class="btn btn-sm btn-outline btn-error">{{ __('procedure.run.markFailed') }}</button>
                                        </div>
                                    </form>
                                @endif
                            @elseif ($canExecute && $runActive && ! $isFinal && ! $step['applicable'] && $step['blockReason'] === null)
                                {{-- Nicht zutreffender bedingter Schritt: schnelle N/A-Erledigung --}}
                                <form method="POST" action="{{ route('procedure-runs.steps.execute', [$run, $sr]) }}" class="mt-3">
                                    @csrf
                                    <button type="submit" name="status" value="n_a" class="btn btn-xs btn-ghost">{{ __('procedure.run.markNa') }}</button>
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
                            <button type="submit" class="btn btn-sm btn-success" @disabled(! empty($missingRequired))>
                                <x-icon name="task_alt" /> {{ __('procedure.run.complete') }}
                            </button>
                        </form>
                        @if ($canAbort)
                            <details class="dropdown dropdown-end">
                                <summary class="btn btn-sm btn-ghost text-error">{{ __('procedure.run.abort') }}</summary>
                                <form method="POST" action="{{ route('procedure-runs.abort', $run) }}"
                                      class="dropdown-content z-10 w-72 space-y-2 rounded-box border border-base-300 bg-base-100 p-3 shadow">
                                    @csrf
                                    <textarea name="reason" rows="2" class="textarea textarea-bordered textarea-sm w-full"
                                              placeholder="{{ __('procedure.print.abortReason') }}"></textarea>
                                    <button type="submit" class="btn btn-xs btn-error">{{ __('procedure.run.abortConfirm') }}</button>
                                </form>
                            </details>
                        @endif
                    </div>
                    @if (! empty($missingRequired))
                        <p class="text-xs text-base-content/50">{{ __('procedure.run.completeHint') }}</p>
                    @endif
                @endif
            </div>
        </div>
    </x-page-shell>
@endsection
