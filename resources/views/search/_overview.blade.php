{{--
  Created on   : Mon Sep 14 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _overview.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- „Bei wem war das?" — Treffer je Kunde/Endkunde (Klick filtert), je
     Quelle und je Schlagwort. Erwartet: $result, $criteria, $types. --}}
<div class="grid gap-4 lg:grid-cols-3">
    @if ($result->aggregates !== [])
        <x-card :title="__('search.aggregate.title')" icon="groups" class="lg:col-span-2">
            <ul class="divide-y divide-base-200">
                @foreach ($result->aggregates as $aggregate)
                    @php
                        $aggregateLink = match (true) {
                            $aggregate->foreignCustomerId !== null => route('search.index', $criteria->toParameters([
                                'foreign_customer' => \App\Support\Sqid::encode(\App\Models\Customer\ForeignCustomer::class, $aggregate->foreignCustomerId),
                                'customer' => null,
                            ])),
                            $aggregate->customerId !== null => route('search.index', $criteria->toParameters([
                                'customer' => \App\Support\Sqid::encode(\App\Models\Customer\Customer::class, $aggregate->customerId),
                            ])),
                            default => null,
                        };
                    @endphp
                    <li class="flex items-center justify-between gap-3 py-2">
                        <span class="min-w-0">
                            @if ($aggregateLink !== null && $aggregate->label() !== null)
                                <a href="{{ $aggregateLink }}" class="link link-hover font-medium">{{ $aggregate->label() }}</a>
                            @else
                                <span class="text-muted">{{ __('search.aggregate.without_customer') }}</span>
                            @endif
                            @if ($aggregate->periodLabel() !== null)
                                <span class="block text-xs text-muted">{{ $aggregate->periodLabel() }}</span>
                            @endif
                        </span>
                        <span class="badge badge-sm shrink-0">{{ trans_choice('search.aggregate.hits', $aggregate->hits, ['count' => $aggregate->hits]) }}</span>
                    </li>
                @endforeach
            </ul>
        </x-card>
    @endif

    @if ($result->typeCounts !== [])
        <x-card :title="__('search.types.title')" icon="category">
            <ul class="space-y-1">
                @foreach ($types as $type)
                    @continue(! isset($result->typeCounts[$type->value]))
                    <li>
                        <a href="{{ route('search.index', $criteria->toParameters(['types' => [$type->value]])) }}"
                           @class(['flex items-center justify-between gap-2 rounded-box px-2 py-1 text-sm hover:bg-base-200', 'bg-base-200 font-medium' => in_array($type, $criteria->types, true)])>
                            <span class="flex items-center gap-2">
                                <x-icon name="{{ $type->icon() }}" class="text-base text-muted" />
                                {{ $type->label() }}
                            </span>
                            <span class="badge badge-sm badge-ghost">{{ $result->typeCounts[$type->value] }}</span>
                        </a>
                    </li>
                @endforeach
            </ul>
        </x-card>
    @endif
</div>

{{-- Schlagwörter der Treffer als Facette (MVP-812): Klick grenzt ein. --}}
@if ($result->tagFacets !== [])
    <x-card :title="__('search.facets.tags')" icon="sell">
        <div class="flex flex-wrap gap-2">
            @foreach ($result->tagFacets as $facet)
                <a href="{{ route('search.index', $criteria->toParameters(['tag' => \App\Support\Sqid::encode(\App\Models\Classification\Tag::class, $facet['id'])])) }}"
                   @class(['badge gap-1', 'badge-primary' => $criteria->tagId === $facet['id'], 'badge-outline' => $criteria->tagId !== $facet['id']])>
                    {{ $facet['name'] }} <span class="opacity-70">{{ $facet['hits'] }}</span>
                </a>
            @endforeach
        </div>
    </x-card>
@endif
