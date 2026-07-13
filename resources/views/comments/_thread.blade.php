{{--
    Comment thread for any commentable model (DiaryEntry, TimeEntry, ...).

    Required props:
      - $parent      : the commentable Eloquent model (must expose ->comments)
      - $storeRoute  : full URL to POST a new comment to
    Optional:
      - $heading     : custom heading (defaults to "Kommentare (N)")
      - $emptyText   : empty-state message
      - $showHeading : bool, default true
--}}
@php
    $showHeading = $showHeading ?? true;
    $comments = $parent->comments;
    $heading = $heading ?? __('Kommentare') . ' (' . $comments->count() . ')';
    $emptyText = $emptyText ?? __('Noch keine Kommentare.');
@endphp

<div class="comment-thread space-y-4">
    @if ($showHeading)
        <h3 class="font-['Space_Grotesk'] text-lg font-semibold">{{ $heading }}</h3>
    @endif

    <div class="space-y-3">
        @forelse ($comments as $comment)
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
                            <x-icon-btn icon="edit" type="button" :label="__('Bearbeiten')"
                                data-toggle-hidden="comment-edit-{{ $comment->id }}" />
                        @endcan
                        @can('delete', $comment)
                            <x-action-form :action="route('comments.destroy', $comment)"
                                method="DELETE"
                                data-confirm-title="{{ __('Kommentar löschen') }}"
                                :confirm="__('Kommentar wird dauerhaft gelöscht.')"
                                :confirm-label="__('Löschen')">
                                <x-icon-btn icon="delete" tone="error" type="submit" :label="__('Löschen')" />
                            </x-action-form>
                        @endcan
                    </div>
                </div>
                <p class="whitespace-pre-wrap text-sm">{{ $comment->body }}</p>

                @can('update', $comment)
                    <form id="comment-edit-{{ $comment->id }}" method="POST"
                        action="{{ route('comments.update', $comment) }}" class="hidden mt-3 space-y-2">
                        @csrf @method('PUT')
                        <textarea name="body" rows="3" class="textarea textarea-bordered textarea-sm w-full">{{ $comment->body }}</textarea>
                        <x-icon-btn icon="save" tone="primary" type="submit" show-label>{{ __('Speichern') }}</x-icon-btn>
                    </form>
                @endcan
            </article>
        @empty
            <p class="text-sm text-base-content/60">{{ $emptyText }}</p>
        @endforelse
    </div>

    @can('create', App\Models\Comment::class)
        <form method="POST" action="{{ $storeRoute }}" class="space-y-2">
            @csrf
            <textarea name="body" rows="3" required maxlength="5000"
                class="textarea textarea-bordered textarea-sm w-full @error('body') ring-2 ring-error/30 @enderror"
                placeholder="{{ __('Kommentar schreiben...') }}">{{ old('body') }}</textarea>
            @error('body')<p class="text-sm text-error">{{ $message }}</p>@enderror
            <x-icon-btn icon="send" tone="primary" size="sm" type="submit" show-label>{{ __('Kommentieren') }}</x-icon-btn>
        </form>
    @endcan
</div>
