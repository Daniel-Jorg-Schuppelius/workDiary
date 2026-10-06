{{--
  Created on   : Fri May 29 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : row.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@aware(['layout' => 'list', 'smallLabels' => false, 'divided' => false])
@props([
    'label' => null,
    'value' => null,
    'full' => false,
    'layout' => 'list',
    'smallLabels' => false,
    'divided' => false,
])

{{--
    Zeile bzw. Zelle eines <x-detail-grid>; Attribute landen am <dd>.
    full: Zelle über alle Spalten (cells, split). layout an der Zeile
    überschreibt das Raster für diese eine Zelle (Textblock in einer
    split-Liste: layout="cells").
--}}

@php
    $hasSlot = trim($slot) !== '';
    $filled = $hasSlot || ($value !== null && $value !== '');
@endphp

@if ($layout === 'cells' || $layout === 'split')
    <div @class([
        'min-w-0' => $layout === 'cells',
        'flex items-baseline justify-between gap-4' => $layout === 'split',
        'border-b border-base-200/70 pb-1 last:border-0 last:pb-0' => $layout === 'split' && $divided,
        'col-span-full' => $full,
    ])>
        <dt @class(['text-muted', 'text-xs' => $smallLabels])>{{ $label }}</dt>
        <dd {{ $attributes }}>{{ $filled ? ($hasSlot ? $slot : $value) : '—' }}</dd>
    </div>
@elseif ($filled)
    <dt class="text-muted">{{ $label }}</dt>
    <dd {{ $attributes }}>{{ $hasSlot ? $slot : $value }}</dd>
@endif
