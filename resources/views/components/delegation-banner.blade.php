{{--
  Created on   : Thu Nov 12 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : delegation-banner.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  <x-delegation-banner /> — Hinweis im Kopf (Feature 202, MVP-1647):
  „Sie vertreten …“ für die Vertretung, „Vertreten durch …“ für die
  vertretene Person. Nennt nie den Anlass der Abwesenheit. Eine Abfrage je
  Anfrage (ActsForResolver, gemerkt); ohne wirksame Vertretung kein Markup.
--}}
@auth
    @php
        $wdUser = auth()->user();
        $wdInvolving = $wdUser instanceof \App\Models\Platform\User
            ? app(\App\Services\Delegation\ActsForResolver::class)->involving($wdUser)
            : collect();
        $wdRepresents = $wdInvolving->filter(fn ($d) => (int) $d->deputy_user_id === (int) $wdUser->id && $d->represented !== null && ! $d->represented->isDeactivated());
        $wdDeputies = $wdInvolving->filter(fn ($d) => (int) $d->represented_user_id === (int) $wdUser->id && $d->deputy !== null && ! $d->deputy->isDeactivated());
        $wdName = static fn ($d, $person): string => $d->valid_until !== null
            ? (string) __('delegation.banner.person_until', ['name' => $person->name, 'date' => $d->valid_until->fdate()])
            : (string) $person->name;
    @endphp
    @if ($wdRepresents->isNotEmpty() || $wdDeputies->isNotEmpty())
        <div role="status" class="border-b border-info/40 bg-info/10 px-4 py-1.5 text-xs" data-delegation-banner>
            <div class="mx-auto flex max-w-7xl flex-wrap items-center gap-x-4 gap-y-1">
                @if ($wdRepresents->isNotEmpty())
                    <span class="inline-flex items-center gap-2">
                        <x-icon name="supervisor_account" class="text-base" />
                        {{ __('delegation.banner.represents', ['names' => $wdRepresents->map(fn ($d) => $wdName($d, $d->represented))->implode(', ')]) }}
                        <a href="{{ route('work-queue.index') }}" class="link link-hover font-medium">{{ __('delegation.banner.work_queue') }}</a>
                    </span>
                @endif
                @if ($wdDeputies->isNotEmpty())
                    <span class="inline-flex items-center gap-2">
                        <x-icon name="person_check" class="text-base" />
                        {{ __('delegation.banner.represented_by', ['names' => $wdDeputies->map(fn ($d) => $wdName($d, $d->deputy))->implode(', ')]) }}
                        <a href="{{ route('delegations.index') }}" class="link link-hover font-medium">{{ __('delegation.banner.manage') }}</a>
                    </span>
                @endif
            </div>
        </div>
    @endif
@endauth
