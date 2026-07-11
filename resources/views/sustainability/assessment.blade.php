@extends('layouts.app')

@section('title', __('Bewertung: :label', ['label' => $assessment->subject_label]))
@section('nav-title', __('ESG-Bewertung'))

@section('content')
<x-page-shell>
    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif
    @if (session('error'))
        <div class="alert alert-error">{{ session('error') }}</div>
    @endif

    <x-page-toolbar :title="$assessment->subject_label . ' · V' . $assessment->version" :badge="__('values.' . $assessment->status)" badge-tone="outline">
        <div class="text-sm text-base-content/70">
            @if ($assessment->total_score !== null)
                {{ __('Score :score / 5', ['score' => $assessment->total_score]) }} ·
                {{ __('Datenqualität: :quality', ['quality' => $assessment->data_quality !== null ? __("values.{$assessment->data_quality}") : '—']) }}
            @endif
            @if ($assessment->assessed_at) · {{ $assessment->assessed_at->fdatetime() }} @endif
        </div>
        <x-slot:actions>
            <x-icon-btn icon="arrow_back" tone="ghost" size="sm" :href="route('sustainability.index')" show-label>{{ __('Übersicht') }}</x-icon-btn>
            @if ($canManage)
                @unless ($assessment->isFinal())
                    <x-action-form :action="route('sustainability.assessments.finalize', $assessment)"
                          :confirm="__('Bewertung finalisieren? Score, Gewichte und Kontext werden eingefroren.')"
                          confirm-icon="lock" confirm-tone="primary" :confirm-label="__('Finalisieren')">
                        <x-icon-btn icon="lock" tone="primary" size="sm" type="submit" show-label>{{ __('Finalisieren') }}</x-icon-btn>
                    </x-action-form>
                @else
                    <x-action-form :action="route('sustainability.assessments.new-version', $assessment)">
                        <x-icon-btn icon="difference" tone="info" size="sm" type="submit" show-label>{{ __('Neue Version') }}</x-icon-btn>
                    </x-action-form>
                @endunless
            @endif
        </x-slot:actions>
    </x-page-toolbar>

    {{-- Alternativenvergleich (MVP-230) --}}
    <x-card :title="__('Vergleich')">
        <form method="GET" action="{{ route('sustainability.assessments.show', $assessment) }}" class="flex flex-wrap items-end gap-2">
            <select name="vergleich" class="select select-sm select-bordered">
                <option value="">{{ __('— Alternative wählen (Gerät A/B, Reparatur vs. Ersatz …) —') }}</option>
                @foreach ($others as $other)
                    <option value="{{ $other->sqid }}" @selected($compare !== null && $compare->id === $other->id)>{{ $other->subject_label }} (V{{ $other->version }}, {{ __("values.{$other->status}") }})</option>
                @endforeach
            </select>
            <button type="submit" class="btn btn-sm">{{ __('Vergleichen') }}</button>
        </form>
        @if ($compare !== null)
            <div class="mt-3 grid gap-4 sm:grid-cols-2">
                @foreach ([$assessment, $compare] as $candidate)
                    <div class="rounded-box border border-base-300 p-3">
                        <div class="font-medium">{{ $candidate->subject_label }} (V{{ $candidate->version }})</div>
                        <div class="mt-1 flex items-center gap-2 text-sm">
                            @if ($candidate->rating)
                                <x-status-badge size="xs" :tone="$candidate->rating === 'green' ? 'success' : ($candidate->rating === 'yellow' ? 'warning' : 'error')">{{ $candidate->total_score }} / 5</x-status-badge>
                            @else
                                <span class="text-base-content/60">{{ __('noch nicht finalisiert') }}</span>
                            @endif
                            <span class="text-xs text-base-content/60">{{ __('Datenqualität: :quality', ['quality' => $candidate->data_quality !== null ? __("values.{$candidate->data_quality}") : '—']) }}</span>
                        </div>
                    </div>
                @endforeach
            </div>
            <p class="mt-2 text-xs text-base-content/60">{{ __('Nachhaltigkeit nie isoliert entscheiden — Kosten/Nutzen/Risiko der Alternativen gehören in die Begründung (z. B. über die Investitionsakte).') }}</p>
        @endif
    </x-card>

    <x-card :title="__('Kriterien (erklärbarer Score)')">
        <div class="overflow-x-auto">
            <table class="table table-sm">
                <thead>
                    <tr>
                        <th>{{ __('Dimension') }}</th>
                        <th>{{ __('Kriterium') }}</th>
                        <th>{{ __('Gewicht') }}</th>
                        <th>{{ __('Score (0–5)') }}</th>
                        <th>{{ __('Datenqualität') }}</th>
                        <th>{{ __('Quelle / Begründung') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($assessment->items as $item)
                        <tr>
                            <td>{{ $item->criterion !== null ? __("values.{$item->criterion->dimension}") : '—' }}</td>
                            <td>{{ $item->criterion->label ?? '—' }}</td>
                            <td>×{{ $item->weight }}</td>
                            @if ($canManage && ! $assessment->isFinal())
                                <td colspan="3">
                                    <form method="POST" action="{{ route('sustainability.assessments.items.update', [$assessment, $item]) }}" class="flex flex-wrap items-center gap-1">
                                        @csrf @method('PUT')
                                        <select name="score" class="select select-xs select-bordered">
                                            @foreach ([5, 4, 3, 2, 1, 0] as $score)
                                                <option value="{{ $score }}" @selected($item->score === $score)>{{ $score }}</option>
                                            @endforeach
                                        </select>
                                        <select name="data_quality" class="select select-xs select-bordered">
                                            <option value="measured" @selected($item->data_quality === 'measured')>{{ __('values.measured') }}</option>
                                            <option value="calculated" @selected($item->data_quality === 'calculated')>{{ __('values.calculated') }}</option>
                                            <option value="estimated" @selected($item->data_quality === 'estimated')>{{ __('values.estimated') }}</option>
                                        </select>
                                        <input name="source_note" maxlength="300" class="input input-xs input-bordered w-40" placeholder="{{ __('Quelle') }}" value="{{ $item->source_note }}">
                                        <input name="justification" maxlength="1000" class="input input-xs input-bordered w-52" placeholder="{{ __('Begründung') }}" value="{{ $item->justification }}">
                                        <button type="submit" class="btn btn-xs">{{ __('OK') }}</button>
                                    </form>
                                </td>
                            @else
                                <td>{{ $item->score ?? '—' }}</td>
                                <td>{{ __("values.{$item->data_quality}") }}@if ($item->data_quality === 'estimated') <span class="badge badge-warning badge-xs">{{ __('Schätzwert') }}</span>@endif</td>
                                <td class="text-xs text-base-content/70">{{ $item->source_note }} {{ $item->justification !== null ? '— ' . $item->justification : '' }}</td>
                            @endif
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @if ($assessment->isFinal() && $assessment->snapshot !== null)
            <p class="mt-2 text-xs text-base-content/60">
                {{ __('Methodik (eingefroren): :scoring · Faktor-Sets: :sets', [
                    'scoring' => (string) data_get($assessment->snapshot, 'methodology.scoring'),
                    'sets' => implode('; ', (array) data_get($assessment->snapshot, 'methodology.factor_sets', [])),
                ]) }}
            </p>
        @endif
    </x-card>
</x-page-shell>
@endsection
