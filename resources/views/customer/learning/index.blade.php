{{--
  Created on   : Fri Aug 28 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Kundenschulungen im Portal (Feature 149, MVP-742). Default-Deny: hier
  erscheinen ausschließlich freigegebene Kurse mit der ausdrücklichen
  Zielgruppe „Kunden".
--}}
@extends('customer.layout')
@section('title', __('learning.title.portal'))
@section('content')
<div class="space-y-4">
    <h1 class="text-xl font-semibold">{{ __('learning.title.portal') }}</h1>

    @if ($categories->isNotEmpty())
        <form method="GET" action="{{ route('customer.learning.index') }}" class="flex flex-wrap items-end gap-2">
            <div class="form-control">
                <label class="label py-0" for="flt-category"><span class="label-text text-xs">{{ __('learning.field.category') }}</span></label>
                <select id="flt-category" name="category" class="select select-sm select-bordered" data-autosubmit>
                    <option value="">{{ __('learning.filter.all_categories') }}</option>
                    @foreach ($categories as $category)
                        <option value="{{ $category->sqid }}" @selected($categoryId === $category->id)>{{ $category->name }}</option>
                    @endforeach
                </select>
            </div>
        </form>
    @endif

    @forelse ($courses as $course)
        @php $enrollment = $enrollments[$course->id] ?? null; @endphp
        <x-card>
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h2 class="text-sm font-semibold">{{ $course->title }}</h2>
                    @if ($course->subtitle)
                        <p class="mt-1 text-sm text-base-content/70">{{ $course->subtitle }}</p>
                    @endif
                    @if ($course->category)
                        <x-status-badge tone="ghost" size="sm" class="mt-1">{{ $course->category->name }}</x-status-badge>
                    @endif
                    {{-- Preis aus dem Artikel und Sternewert ab fünf Antworten (MVP-794). --}}
                    <p class="mt-1 flex flex-wrap items-center gap-2 text-xs text-muted">
                        @if ($course->article?->default_sale_price)
                            <span>{{ __('learning.field.price') }}: {{ $course->article->default_sale_price->withScale(2)->format() }}</span>
                        @endif
                        @if (isset($ratings[$course->id]))
                            <span title="{{ __('learning.help.rating', ['count' => $ratings[$course->id]['count']]) }}">★ {{ number_format($ratings[$course->id]['average'], 1, ',', '') }}</span>
                        @endif
                    </p>
                    @if ($enrollment)
                        <x-status-badge :tone="$enrollment->status->tone()" size="sm" class="mt-2">{{ $enrollment->status->label() }}</x-status-badge>
                    @endif
                </div>
                @if ($enrollment)
                    <x-icon-btn icon="play_arrow" tone="primary" size="sm"
                                :href="route('customer.learning.show', $enrollment)"
                                show-label>{{ __('learning.action.open_course') }}</x-icon-btn>
                @else
                    <div class="flex flex-wrap items-center gap-2">
                        @if (($course->preview_units_count ?? 0) > 0)
                            <x-icon-btn icon="visibility" tone="outline" size="sm"
                                        :href="route('customer.learning.preview', $course)"
                                        show-label>{{ __('learning.action.preview') }}</x-icon-btn>
                        @endif
                        <form method="POST" action="{{ route('customer.learning.enroll', $course) }}">
                            @csrf
                            <x-icon-btn icon="school" tone="primary" size="sm" type="submit" show-label>{{ __('learning.action.enroll') }}</x-icon-btn>
                        </form>
                    </div>
                @endif
            </div>
        </x-card>
    @empty
        <x-empty-state icon="school" :title="__('learning.empty.portal')" />
    @endforelse
</div>
@endsection
