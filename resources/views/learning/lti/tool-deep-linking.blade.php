{{--
  Created on   : Mon Sep 14 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : tool-deep-linking.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  LTI 1.3 als Tool (Feature 149): Kursauswahl für eine fremde Plattform. Ohne
  Benutzerkonto — angeboten wird nur, was die Organisation für LTI freigegeben hat.
  Variablen: $platform, $courses
--}}
@extends('layouts.guest')
@section('title', __('learning.lti_tool.choose_title'))
@section('content')
<div class="w-full space-y-4">
    <div>
        <h1 class="text-xl font-semibold">{{ __('learning.lti_tool.choose_title') }}</h1>
        <p class="mt-1 text-sm text-base-content/70">{{ __('learning.lti_tool.choose_hint', ['platform' => $platform->name]) }}</p>
    </div>

    <form method="POST" action="{{ route('learning.lti.tool.deep-linking.submit') }}" class="space-y-3">
        @csrf
        @forelse ($courses as $course)
            <label class="flex items-start gap-3 rounded-box border border-base-300 p-3">
                <input type="radio" name="course" value="{{ $course->sqid }}" class="radio radio-sm mt-0.5" @checked($loop->first)>
                <span>
                    <span class="block font-medium">{{ $course->title }}</span>
                    @if ($course->subtitle)
                        <span class="block text-xs text-base-content/70">{{ $course->subtitle }}</span>
                    @endif
                </span>
            </label>
        @empty
            <p class="text-sm text-base-content/70">{{ __('learning.lti_tool.no_courses') }}</p>
        @endforelse

        <div class="flex flex-wrap justify-end gap-2">
            <x-button type="submit" tone="ghost" name="course" value="">{{ __('learning.lti_tool.cancel') }}</x-button>
            @if ($courses->isNotEmpty())
                <x-button type="submit">{{ __('learning.lti_tool.choose') }}</x-button>
            @endif
        </div>
    </form>
</div>
@endsection
