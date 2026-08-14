{{--
  Created on   : Mon Jun 15 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _issues.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{--
  Tagesabschluss-Sektion „Lücken & Warnungen" (MVP-015 §2.4, ⛔ vor ⚠).
  Gemeinsamer Partial für Tagesabschluss- und „Heute"-Seite.
  Erwartet aus dem Host-Scope: $issues, $validator.
--}}
@php
    $blockingIssues = array_values(array_filter($issues, fn(array $i) => $i['severity'] === \App\Services\TimeApproval\DayClosureValidator::SEVERITY_BLOCKING));
    $warningIssues = array_values(array_filter($issues, fn(array $i) => $i['severity'] === \App\Services\TimeApproval\DayClosureValidator::SEVERITY_WARNING));
@endphp
<x-card as="section">
    <h2 class="mb-3 flex items-center gap-2 text-base font-semibold">
        <span class="material-symbols-outlined" aria-hidden="true">report</span>
        {{ __('day-close.section.issues') }}
    </h2>
    @if (empty($issues))
        <div role="alert" class="alert alert-success">
            <span class="material-symbols-outlined" aria-hidden="true">check_circle</span>
            <span>{{ __('day-close.hint.no_issues') }}</span>
        </div>
    @else
        <ul class="space-y-2">
            @foreach ($blockingIssues as $issue)
                <li role="alert" class="alert alert-error">
                    <span class="material-symbols-outlined" aria-hidden="true">block</span>
                    <span>{{ $validator->messageFor($issue) }}</span>
                </li>
            @endforeach
            @foreach ($warningIssues as $issue)
                <li role="alert" class="alert alert-warning">
                    <span class="material-symbols-outlined" aria-hidden="true">warning</span>
                    <span>{{ $validator->messageFor($issue) }}</span>
                </li>
            @endforeach
        </ul>
    @endif
</x-card>
