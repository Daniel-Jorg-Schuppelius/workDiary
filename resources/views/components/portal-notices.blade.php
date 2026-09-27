{{--
  Created on   : Sun Sep 27 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : portal-notices.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Mitteilungen im Kundenportal und auf der Statusseite (MVP-915); Text bleibt Klartext. --}}
@props(['notices'])
<div {{ $attributes->merge(['class' => 'flex flex-col gap-3']) }}>
    @foreach ($notices as $notice)
        <div role="status" class="alert alert-{{ $notice->tone }} items-start">
            <x-icon :name="match ($notice->tone) { 'success' => 'check_circle', 'info' => 'info', default => 'campaign' }" />
            <div class="min-w-0">
                <div class="font-semibold">{{ $notice->subject }}</div>
                <div class="text-xs opacity-70">
                    {{ \Carbon\CarbonImmutable::instance($notice->publishedAt)->timezone(\App\Support\Tz::current())->format('d.m.Y H:i') }}
                    @if ($notice->badge) · {{ $notice->badge }} @endif
                </div>
                <p class="mt-1 text-sm whitespace-pre-line">{{ $notice->body }}</p>
            </div>
        </div>
    @endforeach
</div>
