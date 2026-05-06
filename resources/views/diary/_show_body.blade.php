{{-- Diary Detail-Body (für Seite + Dialog). Erwartet: $diary, $legacyEntry (nullable), $isDialog (bool, optional) --}}
@php($isDialog = $isDialog ?? false)

<article>
    <div class="flex flex-wrap items-center justify-between gap-4 mb-6 {{ $isDialog ? 'pr-10' : '' }}">
        <div class="flex flex-wrap items-center gap-3">
            <span @class([
                'badge badge-sm',
                'badge-success' => $diary->statusTone() === 'done',
                'badge-info' => $diary->statusTone() === 'progress',
                'badge-warning' => $diary->statusTone() === 'open',
                'badge-error' => $diary->statusTone() === 'alert',
                'badge-ghost' => $diary->statusTone() === 'neutral',
            ])>{{ $diary->statusLabel() }}</span>
            <span class="text-sm text-base-content/70">{{ optional($diary->user)->name ?? '—' }}</span>
            @if ($diary->is_archived)
                <span class="badge badge-neutral">{{ __('Archiviert') }}{{ $diary->archived_at ? ' · ' . $diary->archived_at->format('d.m.Y') : '' }}</span>
            @endif
            @if ($diary->legacy_id)
                <span class="badge badge-outline badge-warning">
                    Legacy #{{ $diary->legacy_id }}
                </span>
            @endif
        </div>
        <div class="flex gap-2">
            @can('update', $diary)
                <a href="{{ route('diary.edit', $diary) }}" data-entry-modal-trigger class="btn btn-ghost btn-sm">{{ __('Bearbeiten') }}</a>
            @endcan
            @can('archive', $diary)
                @if ($diary->is_archived)
                    <form method="POST" action="{{ route('diary.restore', $diary) }}">
                        @csrf
                        <button class="btn btn-outline btn-sm">{{ __('Wiederherstellen') }}</button>
                    </form>
                @else
                    <form method="POST" action="{{ route('diary.archive', $diary) }}">
                        @csrf
                        <button class="btn btn-outline btn-sm">{{ __('Archivieren') }}</button>
                    </form>
                @endif
            @endcan
            @can('delete', $diary)
                <form
                    method="POST"
                    action="{{ route('diary.destroy', $diary) }}"
                    data-confirm-dialog
                    data-confirm-title="{{ __('Eintrag löschen') }}"
                    data-confirm-message="{{ __('Der Eintrag wird dauerhaft gelöscht. Möchtest du fortfahren?') }}"
                    data-confirm-label="{{ __('Löschen') }}"
                >
                    @csrf @method('DELETE')
                    <button class="btn btn-error btn-outline btn-sm">{{ __('Löschen') }}</button>
                </form>
            @endcan
        </div>
    </div>

    <h2 class="mb-4 font-['Space_Grotesk'] text-2xl font-bold text-base-content">{{ __('Inhalt') }}</h2>
    <div class="whitespace-pre-wrap leading-relaxed text-base-content">{{ $diary->content }}</div>

    @if ($diary->tags->isNotEmpty())
        <div class="mt-4 flex flex-wrap gap-2">
            @foreach ($diary->tags as $tag)
                <a href="{{ route('diary.index', ['tag' => $tag->id]) }}"
                    class="badge badge-outline"
                    @if ($tag->color) style="border-color: {{ $tag->color }}; color: {{ $tag->color }};" @endif>
                    #{{ $tag->name }}
                </a>
            @endforeach
        </div>
    @endif

    @if ($diary->response)
        <div class="mt-6 rounded-box border border-info/30 bg-info/10 p-5">
            <p class="mb-3 text-xs uppercase tracking-[0.2em] text-base-content/65">{{ __('Rückmeldung') }}</p>
            <div class="whitespace-pre-wrap leading-relaxed text-base-content">{{ $diary->response }}</div>
        </div>
    @endif

    <div class="mt-6 grid grid-cols-2 gap-4 text-sm md:grid-cols-4">
        <div class="rounded-xl border border-base-300 bg-base-200 px-4 py-3">
            <p class="mb-1 text-xs text-base-content/60">{{ __('Von') }}</p>
            <p class="text-base-content">{{ $diary->start_at?->format('d.m.Y H:i') ?? '—' }}</p>
        </div>
        <div class="rounded-xl border border-base-300 bg-base-200 px-4 py-3">
            <p class="mb-1 text-xs text-base-content/60">{{ __('Bis') }}</p>
            <p class="text-base-content">{{ $diary->end_at?->format('d.m.Y H:i') ?? '—' }}</p>
        </div>
        <div class="rounded-xl border border-base-300 bg-base-200 px-4 py-3">
            <p class="mb-1 text-xs text-base-content/60">{{ __('Erstellt') }}</p>
            <p class="text-base-content">{{ $diary->created_at->format('d.m.Y H:i') }}</p>
        </div>
        <div class="rounded-xl border border-base-300 bg-base-200 px-4 py-3">
            <p class="mb-1 text-xs text-base-content/60">{{ __('Geändert') }}</p>
            <p class="text-base-content">{{ $diary->updated_at->diffForHumans() }}</p>
        </div>
    </div>
</article>

@if (!empty($legacyEntry))
    <section class="rounded-box border border-base-300 bg-base-100 p-6 shadow-xs">
        <p class="mb-4 font-['Space_Grotesk'] font-semibold text-base-content">Legacy-Original (tagebuch #{{ $legacyEntry->id }})</p>
        <div class="grid gap-4 md:grid-cols-2">
            <div>
                <p class="mb-2 text-xs text-base-content/60">Inhalt (original)</p>
                <p class="text-sm leading-relaxed whitespace-pre-wrap text-base-content/80">{{ truncate($legacyEntry->inhalt, 400) }}</p>
            </div>
            @if ($legacyEntry->antwort)
                <div>
                    <p class="mb-2 text-xs text-base-content/60">Antwort (original)</p>
                    <p class="text-sm leading-relaxed whitespace-pre-wrap text-base-content/80">{{ truncate($legacyEntry->antwort, 400) }}</p>
                </div>
            @endif
        </div>
        <p class="mt-4 text-xs text-base-content/60">Autor: {{ optional($legacyEntry->author)->uname ?? '—' }} · gelesen={{ $legacyEntry->gelesen }}</p>
    </section>
@endif

<section id="comments" class="rounded-box border border-base-300 bg-base-100 p-6 shadow-xs">
    <h3 class="mb-4 font-['Space_Grotesk'] text-lg font-semibold">{{ __('Kommentare') }} ({{ $diary->comments->count() }})</h3>

    <div class="space-y-3 mb-4">
        @forelse ($diary->comments as $comment)
            <article class="rounded-box border border-base-300 bg-base-200 p-4">
                <div class="flex flex-wrap items-center justify-between gap-2 mb-2">
                    <div class="text-sm">
                        <span class="font-semibold">{{ optional($comment->user)->name ?? '—' }}</span>
                        <span class="text-base-content/60">· {{ $comment->created_at->diffForHumans() }}</span>
                        @if ($comment->updated_at->gt($comment->created_at))
                            <span class="text-xs text-base-content/50">({{ __('bearbeitet') }})</span>
                        @endif
                    </div>
                    <div class="flex gap-1">
                        @can('update', $comment)
                            <button type="button" class="btn btn-ghost btn-xs" onclick="document.getElementById('comment-edit-{{ $comment->id }}').classList.toggle('hidden')">{{ __('Bearbeiten') }}</button>
                        @endcan
                        @can('delete', $comment)
                            <form method="POST" action="{{ route('comments.destroy', $comment) }}" class="inline"
                                data-confirm-dialog
                                data-confirm-title="{{ __('Kommentar löschen') }}"
                                data-confirm-message="{{ __('Kommentar wird dauerhaft gelöscht.') }}"
                                data-confirm-label="{{ __('Löschen') }}">
                                @csrf @method('DELETE')
                                <button class="btn btn-ghost btn-xs text-error">{{ __('Löschen') }}</button>
                            </form>
                        @endcan
                    </div>
                </div>
                <p class="whitespace-pre-wrap text-sm">{{ $comment->body }}</p>

                @can('update', $comment)
                    <form id="comment-edit-{{ $comment->id }}" method="POST" action="{{ route('comments.update', $comment) }}" class="hidden mt-3 space-y-2">
                        @csrf @method('PUT')
                        <textarea name="body" rows="3" class="textarea textarea-bordered textarea-sm w-full">{{ $comment->body }}</textarea>
                        <button type="submit" class="btn btn-primary btn-xs">{{ __('Speichern') }}</button>
                    </form>
                @endcan
            </article>
        @empty
            <p class="text-sm text-base-content/60">{{ __('Noch keine Kommentare.') }}</p>
        @endforelse
    </div>

    @can('create', App\Models\Comment::class)
        <form method="POST" action="{{ route('diary.comments.store', $diary) }}" class="space-y-2">
            @csrf
            <textarea name="body" rows="3" required maxlength="5000"
                class="textarea textarea-bordered textarea-sm w-full @error('body') ring-2 ring-error/30 @enderror"
                placeholder="{{ __('Kommentar schreiben...') }}">{{ old('body') }}</textarea>
            @error('body')<p class="text-sm text-error">{{ $message }}</p>@enderror
            <button type="submit" class="btn btn-primary btn-sm">{{ __('Kommentieren') }}</button>
        </form>
    @endcan
</section>

@include('attachments._panel', ['parent' => $diary, 'parentType' => 'diary'])
