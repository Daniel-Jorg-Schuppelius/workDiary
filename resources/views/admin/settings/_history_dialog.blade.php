{{--
  Created on   : Thu Jul 09 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _history_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Dialog: Änderungsverlauf eines System-Settings (MVP-174) --}}
<x-modal
    :title="__('settingsregistry.action.history')"
    :eyebrow="$definition->key"
    icon="history"
    tone="info"
>
    @if ($logs->isEmpty())
        <p class="text-sm text-base-content/60">{{ __('settingsregistry.empty.history') }}</p>
    @else
        <ul class="space-y-2 text-sm">
            @foreach ($logs as $log)
                <li class="rounded-lg border border-base-300/60 p-2">
                    <div class="flex items-center justify-between text-xs text-base-content/60">
                        <span>{{ $log->created_at?->format('d.m.Y H:i') }}</span>
                        <span>{{ $log->event }}</span>
                    </div>
                    <pre class="mt-1 max-h-24 overflow-auto text-xs">{{ json_encode($log->getAttribute('changes'), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                </li>
            @endforeach
        </ul>
    @endif
</x-modal>
