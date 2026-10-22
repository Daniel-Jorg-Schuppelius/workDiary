{{--
  Created on   : Thu Oct 22 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _suggestion_card.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Materialvorschlag aus der Anlagen-Stückliste (Feature 118, MVP-1284) an
  Ticket und Auftrag: fällige Verschleißteile des Geräts. Vorschlag, keine
  Buchung — der Monteur entscheidet. Variablen: $asset (Asset).
--}}
@php
    $dueParts = app(\App\Services\Asset\AssetComponentService::class)->dueComponents($asset);
@endphp
@if ($dueParts->isNotEmpty())
    <div class="rounded-box border border-warning/40 bg-warning/5 px-4 py-3" data-material-suggestion>
        <div class="flex flex-wrap items-center gap-2">
            <x-icon name="build" class="text-warning" />
            <p class="text-sm font-medium">{{ __('asset.components.due.heading') }}</p>
            <span class="flex-1"></span>
            <a class="link text-sm" href="{{ route('assets.components.index', $asset) }}">{{ __('asset.components.title') }}</a>
        </div>
        <p class="mt-1 text-xs text-base-content/70">{{ __('asset.components.due.hint') }}</p>
        <ul class="mt-2 space-y-1 text-sm">
            @foreach ($dueParts as $part)
                <li class="flex flex-wrap items-center gap-2">
                    <span class="font-medium">{{ $part->displayName() }}</span>
                    @if ($part->quantity !== null)<span class="text-muted">{{ \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat((float) $part->quantity, 2) }} {{ $part->unit }}</span>@endif
                    @if ($part->position !== null)<span class="text-muted">{{ $part->position }}</span>@endif
                    <x-status-badge tone="warning" outline>{{ optional($part->dueOn())->fdate() ?? '—' }}</x-status-badge>
                </li>
            @endforeach
        </ul>
    </div>
@endif
