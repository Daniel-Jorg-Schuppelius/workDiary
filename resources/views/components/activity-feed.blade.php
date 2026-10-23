{{--
  Created on   : Fri Oct 23 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : activity-feed.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  <x-activity-feed :subject="$model" /> — Änderungen und Journal-Ereignisse
  eines Objekts (Feature 183); ohne Einträge kein Markup.
--}}
@props(['subject', 'limit' => 20])

@php($feed = app(\App\Services\Timeline\ActivityFeedService::class)->forSubject($subject, (int) $limit))

@if ($feed['items'] !== [])
    <x-card as="section" id="activity" :title="__('activity.subject_title')" icon="history">
        @include('timeline._items', ['items' => $feed['items']])
        @if ($feed['hasMore'])
            <p class="mt-2 text-xs text-muted">
                @can('viewAny', \App\Models\Audit\AuditLog::class)
                    <a class="link link-hover" href="{{ route('audit.index') }}">{{ __('activity.more_in_audit') }}</a>
                @else
                    {{ __('activity.more') }}
                @endcan
            </p>
        @endif
    </x-card>
@endif
