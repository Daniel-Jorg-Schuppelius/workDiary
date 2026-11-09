{{--
  Created on   : Wed Apr 29 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : show.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@extends('layouts.app')
@section('title', \CommonToolkit\Helper\Data\StringHelper::truncate($entry->inhalt ?? '', 60) . ' — ' . config('app.name', 'WorkDiary'))
@section('nav-title', __('Eintrag') . ' #' . $entry->id)

@include('partials.page-fill')

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
    <div class="mx-auto flex min-h-0 w-full max-w-3xl grow basis-0 flex-col gap-4 overflow-x-clip overflow-y-auto">
        <div class="min-h-(--wd-table-min-h) flex-1 overflow-hidden rounded-box border border-base-300 bg-base-100 shadow-xs">
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
