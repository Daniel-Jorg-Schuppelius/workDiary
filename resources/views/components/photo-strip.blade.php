{{--
  Created on   : Sun Jul 19 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : photo-strip.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{--
  <x-photo-strip> — Fotos je Protokollpunkt (MVP-023 §3.1/§3.3; Vollaudit
  2026-07, H7): je Phase eine Leiste mit Vorschau, Caption, Reihenfolge
  (nach vorn) und Löschen; Vorher/Nachher stehen nebeneinander. Upload mit
  Phase + Caption. Variablen: $item (ProtocolItem), $canManage (bool).
--}}
@props(['item', 'canManage' => false])

@php
    use App\Enums\Protocol\ProtocolItemPhotoPhase;
    $byPhase = $item->photos->sortBy('sort_order')->groupBy(fn($p) => $p->phase->value);
    $phases = collect(ProtocolItemPhotoPhase::cases())->filter(fn($phase) => $byPhase->has($phase->value));
@endphp

<div class="space-y-2">
    @if ($phases->isNotEmpty())
        <div class="grid gap-3 sm:grid-cols-2">
            @foreach ($phases as $phase)
                <div>
                    <div class="mb-1 text-xs font-medium uppercase tracking-wide text-muted">{{ $phase->label() }}</div>
                    <div class="flex flex-wrap gap-2">
                        @foreach ($byPhase[$phase->value] as $photo)
                            @php($att = $photo->attachment)
                            <figure class="w-24">
                                @if ($att)
                                    <a href="{{ \App\Http\Controllers\AttachmentController::downloadUrl($att) }}" target="_blank" rel="noopener">
                                        <img src="{{ \App\Http\Controllers\AttachmentController::downloadUrl($att) }}"
                                             alt="{{ $photo->caption ?? $att->original_name }}"
                                             class="h-20 w-24 rounded-box border border-base-300 object-cover" loading="lazy">
                                    </a>
                                @endif
                                <figcaption class="mt-0.5 truncate text-[11px] text-muted" title="{{ $photo->caption }}">
                                    {{ $photo->caption ?? '—' }}
                                </figcaption>
                                @if ($canManage)
                                    <div class="mt-0.5 flex items-center gap-1">
                                        @if (! $loop->first)
                                            <x-action-form :action="route('protocols.items.photos.promote', $photo)" method="POST">
                                                <button type="submit" class="btn btn-ghost btn-xs px-1" title="{{ __('Nach vorn') }}">
                                                    <x-icon name="arrow_back" class="text-sm" />
                                                </button>
                                            </x-action-form>
                                        @endif
                                        <button type="button" class="btn btn-ghost btn-xs px-1"
                                                data-toggle-hidden="caption-photo-{{ $photo->getRouteKey() }}"
                                                title="{{ __('Caption bearbeiten') }}">
                                            <x-icon name="edit" class="text-sm" />
                                        </button>
                                        <form method="POST" action="{{ route('protocols.items.photos.caption', $photo) }}" class="hidden"
                                              id="caption-photo-{{ $photo->getRouteKey() }}">
                                            @csrf
                                            @method('PATCH')
                                            <input name="caption" maxlength="180" value="{{ $photo->caption }}"
                                                   class="input input-bordered input-xs w-24" />
                                        </form>
                                        <x-action-form :action="route('protocols.items.photos.destroy', $photo)" method="DELETE"
                                                       :confirm="__('Foto wirklich entfernen?')">
                                            <button type="submit" class="btn btn-ghost btn-xs px-1 text-error" title="{{ __('Entfernen') }}">
                                                <x-icon name="delete" class="text-sm" />
                                            </button>
                                        </x-action-form>
                                    </div>
                                @endif
                            </figure>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    @if ($canManage)
        <details class="text-sm">
            <summary class="cursor-pointer text-base-content/70">{{ __('Foto hinzufügen') }}</summary>
            <form method="POST" action="{{ route('protocols.items.photos.store', $item) }}"
                  enctype="multipart/form-data" class="mt-2 flex flex-wrap items-end gap-2">
                @csrf
                <input type="file" name="photo" accept="image/*" capture="environment" required
                       class="file-input file-input-bordered file-input-sm" />
                <select name="phase" class="select select-bordered select-sm">
                    @foreach (ProtocolItemPhotoPhase::cases() as $phase)
                        <option value="{{ $phase->value }}">{{ $phase->label() }}</option>
                    @endforeach
                </select>
                <input name="caption" maxlength="180" placeholder="{{ __('Caption (optional)') }}"
                       class="input input-bordered input-sm w-44" />
                <button type="submit" class="btn btn-primary btn-sm">{{ __('Hochladen') }}</button>
            </form>
        </details>
    @endif
</div>
