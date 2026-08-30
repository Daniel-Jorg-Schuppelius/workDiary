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

    @forelse ($courses as $course)
        @php $enrollment = $enrollments[$course->id] ?? null; @endphp
        <x-card>
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h2 class="text-sm font-semibold">{{ $course->title }}</h2>
                    @if ($course->subtitle)
                        <p class="mt-1 text-sm text-base-content/70">{{ $course->subtitle }}</p>
                    @endif
                    @if ($enrollment)
                        <x-status-badge :tone="$enrollment->status->tone()" size="sm" class="mt-2">{{ $enrollment->status->label() }}</x-status-badge>
                    @endif
                </div>
                @if ($enrollment)
                    <x-icon-btn icon="play_arrow" tone="primary" size="sm"
                                :href="route('customer.learning.show', $enrollment)"
                                show-label>{{ __('learning.action.open_course') }}</x-icon-btn>
                @else
                    <form method="POST" action="{{ route('customer.learning.enroll', $course) }}">
                        @csrf
                        <x-icon-btn icon="school" tone="primary" size="sm" type="submit" show-label>{{ __('learning.action.enroll') }}</x-icon-btn>
                    </form>
                @endif
            </div>
        </x-card>
    @empty
        <x-empty-state icon="school" :title="__('learning.empty.portal')" />
    @endforelse
</div>
@endsection
