<x-pdf-layout pdf-type="idea_map" :pdf-title="$map->title">
    <h1>{{ $map->title }}</h1>
    <div class="meta">
        {{ __('ideas.col.owner') }}: <strong>{{ $map->owner?->name }}</strong>
        · {{ __('ideas.export.generated_at') }}: <strong>{{ $generatedAt->format('d.m.Y H:i') }}</strong>
    </div>
    @if ($map->description)
        <p style="margin-top: 6pt;">{{ $map->description }}</p>
    @endif

    <h2>{{ __('ideas.editor.outline') }}</h2>
    @if ($root === null)
        <p class="meta">{{ __('ideas.outline.empty') }}</p>
    @else
        @php
            // Gliederungsdarstellung (MVP-110): eingerückter Baum, Status und
            // Farbe als TEXT (Farbe nie einziger Informationsträger).
            $renderNode = function ($node, $depth) use (&$renderNode, $byParent) {
                echo '<div style="margin-left:' . ($depth * 14) . 'pt; margin-top:' . ($depth === 0 ? '0' : '3') . 'pt;">';
                echo '<strong>' . e($node->title) . '</strong>';
                $metaParts = [];
                if ($node->node_status) {
                    $metaParts[] = e($node->node_status);
                }
                if ($node->color->value !== 'default') {
                    $metaParts[] = e($node->color->label());
                }
                if ($metaParts !== []) {
                    echo ' <span style="color:#555; font-size:9pt;">[' . implode(' · ', $metaParts) . ']</span>';
                }
                if ($node->note) {
                    echo '<div style="color:#333; font-size:9pt; margin-left:6pt;">' . nl2br(e($node->note)) . '</div>';
                }
                echo '</div>';
                foreach ($byParent->get($node->id, collect())->sortBy('sort_order') as $child) {
                    $renderNode($child, $depth + 1);
                }
            };
            $renderNode($root, 0);
        @endphp
    @endif

    <div class="meta" style="margin-top: 16pt;">{{ __('ideas.export.footer_note') }}</div>
</x-pdf-layout>
