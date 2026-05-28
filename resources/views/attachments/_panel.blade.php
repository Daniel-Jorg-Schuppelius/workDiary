@php
    use App\Http\Controllers\AttachmentController;
    use App\Models\Attachment;
    /** @var \Illuminate\Database\Eloquent\Model $parent */
    /** @var string $parentType */
    $attachments = $parent->attachments;
@endphp

<section id="attachments" class="rounded-box border border-base-300 bg-base-100 p-6 shadow-xs">
    <h3 class="mb-4 font-['Space_Grotesk'] text-lg font-semibold">{{ __('Anhänge') }} ({{ $attachments->count() }})</h3>

    <div class="space-y-2 mb-4">
        @forelse ($attachments as $attachment)
            <div class="flex flex-wrap items-center justify-between gap-2 rounded-box border border-base-300 bg-base-200 p-3">
                <div class="flex min-w-0 items-center gap-3">
                    <x-icon :name="$attachment->isImage() ? 'image' : 'attach_file'" class="text-base-content/60 text-xl" />
                    <div class="min-w-0">
                        <a href="{{ AttachmentController::downloadUrl($attachment) }}" class="link link-primary text-sm break-all">{{ $attachment->original_name }}</a>
                        <p class="text-xs text-base-content/60">
                            {{ $attachment->humanSize() }}
                            · {{ optional($attachment->uploader)->name ?? '—' }}
                            · {{ $attachment->created_at->diffForHumans() }}
                        </p>
                    </div>
                </div>
                @can('delete', $attachment)
                    <form method="POST" action="{{ route('attachments.destroy', $attachment) }}" class="inline"
                        data-confirm-dialog
                        data-confirm-title="{{ __('Anhang löschen') }}"
                        data-confirm-message="{{ __('Anhang wird dauerhaft gelöscht.') }}"
                        data-confirm-label="{{ __('Löschen') }}">
                        @csrf @method('DELETE')
                        <x-icon-btn icon="delete" tone="error" type="submit" :label="__('Löschen')" />
                    </form>
                @endcan
            </div>
        @empty
            <x-empty-state icon='<span class="material-symbols-outlined" aria-hidden="true">attach_file</span>'
                           :title="__('Noch keine Anhänge')"
                           :message="null" compact />
        @endforelse
    </div>

    @can('create', Attachment::class)
        <form method="POST" action="{{ route('attachments.store', ['type' => $parentType, 'id' => $parent->sqid]) }}" enctype="multipart/form-data" class="flex flex-wrap items-center gap-2">
            @csrf
            <input type="file" name="file" required class="file-input file-input-bordered file-input-sm flex-1 min-w-50 @error('file') file-input-error @enderror" />
            <x-icon-btn icon="upload" tone="primary" size="sm" type="submit" show-label>{{ __('Hochladen') }}</x-icon-btn>
            @error('file')<p class="basis-full text-sm text-error">{{ $message }}</p>@enderror
        </form>
        <p class="mt-2 text-xs text-base-content/50">{{ __('Max. 25 MB. Erlaubt: jpg, png, gif, webp, pdf, txt, csv, log, zip, docx, xlsx.') }}</p>
    @endcan
</section>
