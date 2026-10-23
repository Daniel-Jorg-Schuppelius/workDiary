{{--
  Created on   : Fri Oct 23 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _list.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Gemeinsame Liste eines Cockpit-Panels (Feature 182).
  Erwartet: $id, $title, $icon, $count (Gesamtzahl), $rows
  (list<array{title: string, url: ?string, subtitle: ?string, badge: ?string, tone: ?string, date: ?string}>),
  optional $summary (Kurztext über der Liste).
--}}
<x-card as="section" :id="$id" :title="$title" :icon="$icon" :count="$count" padding="p-0">
    @if (($summary ?? null) !== null)
        <p class="border-b border-base-300 px-4 py-2 text-sm text-muted">{{ $summary }}</p>
    @endif
    <ul class="divide-y divide-base-300">
        @foreach ($rows as $row)
            <li class="flex flex-wrap items-center justify-between gap-2 px-4 py-2 text-sm">
                <div class="min-w-0">
                    @if ($row['url'] !== null)
                        <a class="link link-hover font-medium" href="{{ $row['url'] }}">{{ $row['title'] }}</a>
                    @else
                        <span class="font-medium">{{ $row['title'] }}</span>
                    @endif
                    @if ($row['subtitle'] !== null)
                        <div class="truncate text-xs text-muted">{{ $row['subtitle'] }}</div>
                    @endif
                </div>
                <div class="flex items-center gap-2">
                    @if ($row['date'] !== null)
                        <span class="text-xs tabular-nums text-muted">{{ $row['date'] }}</span>
                    @endif
                    @if ($row['badge'] !== null)
                        <x-status-badge size="xs" :tone="$row['tone'] ?? 'ghost'">{{ $row['badge'] }}</x-status-badge>
                    @endif
                </div>
            </li>
        @endforeach
    </ul>
    @if ($count > count($rows))
        <p class="border-t border-base-300 px-4 py-2 text-xs text-muted">{{ __('cockpit.more', ['count' => $count - count($rows)]) }}</p>
    @endif
</x-card>
