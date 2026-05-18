@extends('layouts.app')
@section('title', truncate($entry->inhalt ?? '', 60) . ' — ' . config('app.name', 'WorkDiary'))
@section('nav-title', __('Eintrag') . ' #' . $entry->id)

@section('content')
    @php
        $weekAnchorDate = $entry->von ?? $entry->bis ?? $entry->aktuell;
        $weekDate = request()->query('week_date') ?: $weekAnchorDate?->format('o-\\WW');
        $listParams = [];
        if (preg_match('/^(\d{4})-W(\d{2})$/', (string) $weekDate, $matches) === 1) {
            $listMonday = now()->setISODate((int) $matches[1], (int) $matches[2], 1)->startOfDay();
            $listParams = [
                'from' => $listMonday->format('Y-m-d'),
                'to' => $listMonday->copy()->addDays(6)->format('Y-m-d'),
            ];
        }
    @endphp
    <div class="mx-auto flex h-[calc(100dvh-11rem)] w-full max-w-3xl flex-col gap-4">
        <div class="min-h-0 flex-1 overflow-hidden rounded-box border border-base-300 bg-base-100 shadow-xs">
            <article class="h-full overflow-auto p-6 md:p-8">
                @include('legacy.diary._show_body', ['isDialog' => false])
            </article>
        </div>

        <div class="flex-none flex flex-wrap items-center justify-center gap-3">
            <x-icon-btn icon="arrow_back" size="sm"
                        :href="route('legacy.diary.index', $listParams)"
                        show-label>{{ __('Zurück zur Legacy-Liste') }}</x-icon-btn>
            <x-icon-btn icon="calendar_view_week" tone="primary" size="sm"
                        :href="route('legacy.diary.week', array_filter(['week_date' => $weekDate]))"
                        show-label>{{ __('Zur Wochenansicht') }}</x-icon-btn>
        </div>
    </div>
@endsection
