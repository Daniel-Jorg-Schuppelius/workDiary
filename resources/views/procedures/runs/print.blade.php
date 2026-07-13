{{--
  Created on   : Sun Jun 14 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : print.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Druckbare Read-Only-Ansicht eines Prozedurlaufs (Feature 026, MVP-025
  §8.8). Standalone-HTML mit Print-CSS (Muster: diary/case-file.blade.php):
  Kopf (Vorlage/Version/Subjekt/Status), Schrittliste mit Ergebnis,
  Vier-Augen-Bestätigern, Abweichungen und Backup-Nachweisen.
--}}
@php
    /** @var \App\Models\ProcedureRun $run */
    $version = $run->templateVersion;
    $tpl = $version?->template;
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <title>{{ __('procedure.print.title') }} #{{ $run->id }} — WorkDiary</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; font-size: 12px; color: #111; margin: 24px auto; max-width: 960px; }
        h1 { font-size: 20px; margin: 0 0 4px; }
        h2 { font-size: 14px; margin: 22px 0 8px; padding-bottom: 4px; border-bottom: 2px solid #111; text-transform: uppercase; letter-spacing: 0.06em; }
        .meta { color: #555; font-size: 10px; margin-bottom: 14px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 6px; }
        th, td { padding: 5px 8px; border: 1px solid #ddd; vertical-align: top; text-align: left; }
        th { background: #f5f5f5; font-weight: 600; white-space: nowrap; }
        .kv th { width: 200px; background: #f5f5f5; }
        .muted { color: #666; }
        .pre { white-space: pre-wrap; }
        .badge { display: inline-block; padding: 1px 6px; border: 1px solid #888; border-radius: 8px; font-size: 10px; white-space: nowrap; }
        .badge-done { border-color: #15803d; color: #15803d; }
        .badge-failed { border-color: #b91c1c; color: #b91c1c; }
        .badge-deviated { border-color: #b45309; color: #b45309; }
        .badge-pending { border-color: #888; color: #666; }
        .step { page-break-inside: avoid; margin-bottom: 4px; }
        .actions { margin: 8px 0 16px; }
        .btn { padding: 6px 12px; border: 1px solid #555; background: #fff; cursor: pointer; text-decoration: none; color: #111; display: inline-block; }
        @media print {
            .no-print { display: none; }
            body { margin: 0; max-width: none; }
            h2 { page-break-after: avoid; }
        }
    </style>
</head>
<body>
    <div class="actions no-print">
        <button class="btn" data-print>{{ __('procedure.action.print') }}</button>
        @if ($backUrl)
            <a class="btn" href="{{ $backUrl }}">{{ __('procedure.action.back') }}</a>
        @endif
    </div>

    {{-- Kopf --}}
    <h1>{{ __('procedure.print.title') }}: {{ $tpl?->name ?? '—' }}</h1>
    <div class="meta">
        {{ __('procedure.print.generatedAt') }} {{ $generatedAt->format('Y-m-d H:i') }}
        — {{ __('procedure.print.run') }} #{{ $run->id }}
        — {{ __('procedure.field.status') }}: {{ $run->status->label() }}
    </div>

    <h2>{{ __('procedure.print.overview') }}</h2>
    <table class="kv">
        <tr><th>{{ __('procedure.field.name') }}</th><td>{{ $tpl?->name ?? '—' }} <span class="muted">({{ $tpl?->code ?? '—' }})</span></td></tr>
        <tr><th>{{ __('procedure.field.currentVersion') }}</th><td>v{{ $version?->version ?? '—' }} @if($version?->risk_level)— {{ $version->risk_level->label() }}@endif</td></tr>
        <tr><th>{{ __('procedure.print.subject') }}</th><td>
            @if ($subject instanceof \App\Models\DiaryEntry)
                {{ __('procedure.print.diaryEntry') }} #{{ $subject->id }} — {{ \CommonToolkit\Helper\Data\StringHelper::truncate((string) $subject->content, 80) }}
            @else
                {{ \App\Support\EntityType::label($run->subject_type) }} #{{ $run->subject_id }}
            @endif
        </td></tr>
        <tr><th>{{ __('procedure.field.status') }}</th><td>{{ $run->status->label() }}</td></tr>
        <tr><th>{{ __('procedure.print.assignee') }}</th><td>{{ optional($run->assignee)->name ?? '—' }}</td></tr>
        <tr><th>{{ __('procedure.print.createdBy') }}</th><td>{{ optional($run->createdBy)->name ?? '—' }}</td></tr>
        <tr><th>{{ __('procedure.print.startedAt') }}</th><td>{{ optional($run->started_at)->format('Y-m-d H:i') ?? '—' }}</td></tr>
        <tr><th>{{ __('procedure.print.completedAt') }}</th><td>{{ optional($run->completed_at)->format('Y-m-d H:i') ?? '—' }}</td></tr>
        @if ($run->abort_reason)
            <tr><th>{{ __('procedure.print.abortReason') }}</th><td class="pre">{{ $run->abort_reason }}</td></tr>
        @endif
    </table>

    {{-- Schritte --}}
    <h2>{{ __('procedure.title.steps') }}</h2>
    <table>
        <thead>
            <tr>
                <th style="width:32px">#</th>
                <th>{{ __('procedure.field.stepLabel') }}</th>
                <th style="width:120px">{{ __('procedure.field.stepType') }}</th>
                <th style="width:110px">{{ __('procedure.field.status') }}</th>
                <th style="width:150px">{{ __('procedure.print.executedBy') }}</th>
                <th style="width:150px">{{ __('procedure.field.secondPerson') }}</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($stepRuns as $sr)
                @php
                    $def = $sr->stepDef;
                    $statusClass = match ($sr->status->value) {
                        'done', 'n_a' => 'badge-done',
                        'failed' => 'badge-failed',
                        'deviated' => 'badge-deviated',
                        default => 'badge-pending',
                    };
                @endphp
                <tr class="step">
                    <td>{{ $def?->sort_order ?? '—' }}</td>
                    <td>
                        <strong>{{ $def?->label ?? __('procedure.print.unknownStep') }}</strong>
                        @if ($def?->required)<span class="badge">{{ __('procedure.field.required') }}</span>@endif
                        @if ($def?->requires_second_person)<span class="badge">{{ __('procedure.field.secondPerson') }}</span>@endif
                        @if ($def && data_get($def->config, 'depends_on.step_code'))
                            <div class="muted" style="font-size:9px">{{ __('procedure.print.dependsOn') }}: {{ data_get($def->config, 'depends_on.step_code') }}@if(data_get($def->config, 'depends_on.equals')) = {{ data_get($def->config, 'depends_on.equals') }}@endif</div>
                        @endif
                        @if ($sr->note)<div class="muted pre" style="font-size:10px">{{ $sr->note }}</div>@endif
                        @if (!empty($sr->value_json))<div class="muted" style="font-size:10px">{{ json_encode($sr->value_json, JSON_UNESCAPED_UNICODE) }}</div>@endif
                    </td>
                    <td>{{ $def?->step_type?->label() ?? '—' }}</td>
                    <td><span class="badge {{ $statusClass }}">{{ $sr->status->label() }}</span></td>
                    <td>{{ optional($sr->executedBy)->name ?? '—' }}<br><span class="muted" style="font-size:9px">{{ optional($sr->executed_at)->format('Y-m-d H:i') }}</span></td>
                    <td>
                        @if ($sr->second_person_user_id)
                            {{ optional($sr->secondPerson)->name ?? '—' }}<br>
                            <span class="muted" style="font-size:9px">{{ $sr->second_person_signed_at ? __('procedure.print.signedAt') . ' ' . $sr->second_person_signed_at->format('Y-m-d H:i') : __('procedure.print.notSigned') }}</span>
                        @else
                            <span class="muted">—</span>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="muted">{{ __('procedure.print.noSteps') }}</td></tr>
            @endforelse
        </tbody>
    </table>

    {{-- Abweichungen --}}
    @if ($deviations->isNotEmpty())
        <h2>{{ __('procedure.print.deviations') }}</h2>
        <table>
            <thead>
                <tr>
                    <th>{{ __('procedure.field.stepLabel') }}</th>
                    <th style="width:130px">{{ __('procedure.print.deviationType') }}</th>
                    <th style="width:90px">{{ __('procedure.print.severity') }}</th>
                    <th>{{ __('procedure.print.reason') }}</th>
                    <th style="width:130px">{{ __('procedure.print.action') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($stepRuns as $sr)
                    @php $dev = $deviations->get($sr->id); @endphp
                    @if ($dev)
                        <tr class="step">
                            <td>{{ $sr->stepDef?->label ?? '—' }}</td>
                            <td>{{ $dev->deviation_type->value }}</td>
                            <td>{{ $dev->severity->value }}</td>
                            <td class="pre">{{ $dev->reason_text }}</td>
                            <td>
                                {{ $dev->proposed_action?->value ?? '—' }}
                                @if ($dev->risk_accepted_at)
                                    <div class="muted" style="font-size:9px">{{ __('procedure.print.riskAccepted') }}: {{ optional($dev->riskAcceptedBy)->name }} ({{ $dev->risk_accepted_at->format('Y-m-d') }})</div>
                                @endif
                            </td>
                        </tr>
                    @endif
                @endforeach
            </tbody>
        </table>
    @endif

    {{-- Backup-Nachweise --}}
    @if ($backupProofs->isNotEmpty())
        <h2>{{ __('procedure.print.backups') }}</h2>
        <table>
            <thead>
                <tr>
                    <th>{{ __('procedure.print.source') }}</th>
                    <th style="width:110px">{{ __('procedure.print.scope') }}</th>
                    <th style="width:130px">{{ __('procedure.print.takenAt') }}</th>
                    <th style="width:90px">{{ __('procedure.print.size') }}</th>
                    <th style="width:110px">{{ __('procedure.print.verified') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($backupProofs as $proof)
                    <tr class="step">
                        <td>{{ $proof->source_label }}</td>
                        <td>{{ $proof->backup_scope->value }}</td>
                        <td>{{ $proof->taken_at->format('Y-m-d H:i') }}</td>
                        <td>{{ number_format($proof->size_bytes / 1024, 1) }} KB</td>
                        <td>
                            @if ($proof->verified)
                                <span class="badge badge-done">{{ __('procedure.print.verifiedYes') }}</span>
                                <div class="muted" style="font-size:9px">{{ optional($proof->verifiedBy)->name }}</div>
                            @else
                                <span class="badge badge-pending">{{ __('procedure.print.verifiedNo') }}</span>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
@include('partials.print-script')
</body>
</html>
