{{--
  Created on   : Thu Aug 06 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : presence.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Teams-Presence-Panel der Anwesenheitsseite (Feature 102, F).
     Variablen: $members (Collection<User>), $presence (email → availability). --}}
<div class="mb-3 rounded-box border border-base-300 bg-base-100 p-3" data-msgraph-presence>
    <h3 class="mb-2 text-sm font-semibold">{{ __('msgraph.presence.heading') }}</h3>
    <ul class="flex flex-wrap gap-x-4 gap-y-1 text-sm">
        @foreach ($members as $member)
            @php
                $state = $presence[strtolower((string) $member->email)] ?? 'PresenceUnknown';
                $tone = match ($state) {
                    'Available', 'AvailableIdle' => 'bg-success',
                    'Busy', 'BusyIdle', 'DoNotDisturb' => 'bg-error',
                    'Away', 'BeRightBack' => 'bg-warning',
                    'Offline' => 'bg-base-content/30',
                    default => 'bg-base-content/20',
                };
            @endphp
            <li class="flex items-center gap-1.5" title="{{ __('msgraph.presence.state.' . $state) !== 'msgraph.presence.state.' . $state ? __('msgraph.presence.state.' . $state) : $state }}">
                <span class="inline-block h-2.5 w-2.5 rounded-full {{ $tone }}" aria-hidden="true"></span>
                <span>{{ $member->name }}</span>
            </li>
        @endforeach
    </ul>
</div>
