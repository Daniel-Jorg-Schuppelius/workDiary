{{--
  Created on   : Thu Nov 12 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _tree.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Seiten mit Unterseiten im Baum der Wissensdatenbank (Feature 191).
  Variablen: $nodes (list<array{page, children}>), $depth (Einrückung).
--}}
@foreach ($nodes as $node)
    @php $treePage = $node['page']; @endphp
    <li>
        <a href="{{ route('knowledge.pages.show', $treePage) }}" class="flex items-center gap-2 rounded-box px-2 py-1 hover:bg-base-200"
           style="padding-inline-start: {{ $depth * 0.9 }}rem">
            <x-icon :name="$treePage->kind->icon()" class="text-muted" />
            <span class="min-w-0 flex-1 truncate">{{ $treePage->title }}</span>
            @if ($treePage->status !== \App\Enums\Knowledge\KnowledgePageStatus::Valid)
                <x-status-badge :tone="$treePage->status->tone()" size="xs">{{ $treePage->status->label() }}</x-status-badge>
            @endif
        </a>
        @if ($node['children'] !== [])
            <ul class="space-y-0.5" role="list">
                @include('knowledge-pages._tree', ['nodes' => $node['children'], 'depth' => $depth + 1])
            </ul>
        @endif
    </li>
@endforeach
