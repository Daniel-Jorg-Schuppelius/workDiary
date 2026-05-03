<?php

namespace App\Http\Controllers;

use App\Models\Attachment;
use App\Models\Comment;
use App\Models\DiaryEntry;
use App\Models\EmergencyAssignment;
use App\Models\OnCallShift;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class AttachmentController extends Controller {
    private const MAX_BYTES = 25 * 1024 * 1024; // 25 MB

    private const ALLOWED_EXTENSIONS = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'pdf', 'txt', 'csv', 'log', 'zip', 'docx', 'xlsx'];

    private const TYPE_MAP = [
        'diary' => DiaryEntry::class,
        'comment' => Comment::class,
        'shift' => OnCallShift::class,
        'assignment' => EmergencyAssignment::class,
    ];

    public function store(Request $request, string $type, int $id): RedirectResponse {
        Gate::authorize('create', Attachment::class);

        $parent = $this->resolveParent($type, $id);

        $request->validate([
            'file' => ['required', 'file', 'max:' . (self::MAX_BYTES / 1024)],
        ]);

        $file = $request->file('file');
        $ext = strtolower($file->getClientOriginalExtension() ?: $file->extension());
        if (! in_array($ext, self::ALLOWED_EXTENSIONS, true)) {
            return back()->withErrors(['file' => __('Dateityp nicht erlaubt.')]);
        }

        $folder = 'attachments/' . now()->format('Y/m');
        $filename = Str::uuid()->toString() . '.' . $ext;
        $path = $file->storeAs($folder, $filename, 'local');

        /** @var \App\Models\DiaryEntry|\App\Models\Comment|\App\Models\OnCallShift|\App\Models\EmergencyAssignment $parent */
        $parent->attachments()->create([
            'user_id' => Auth::id(),
            'disk' => 'local',
            'path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'mime' => $file->getClientMimeType(),
            'size' => $file->getSize(),
        ]);

        return back()->with('success', __('Anhang hochgeladen.'));
    }

    public function download(Request $request, Attachment $attachment): BinaryFileResponse {
        if (! $request->hasValidSignature()) {
            abort(403);
        }

        Gate::authorize('view', $attachment);

        $disk = Storage::disk($attachment->disk);
        if (! $disk->exists($attachment->path)) {
            abort(404);
        }

        return response()->download($disk->path($attachment->path), $attachment->original_name);
    }

    public function destroy(Attachment $attachment): RedirectResponse {
        Gate::authorize('delete', $attachment);

        Storage::disk($attachment->disk)->delete($attachment->path);
        $attachment->delete();

        return back()->with('success', __('Anhang gelöscht.'));
    }

    /**
     * Generate a temporary signed download URL (15 min).
     */
    public static function downloadUrl(Attachment $attachment): string {
        return URL::temporarySignedRoute('attachments.download', now()->addMinutes(15), ['attachment' => $attachment->id]);
    }

    private function resolveParent(string $type, int $id): Model {
        $class = self::TYPE_MAP[$type] ?? null;
        if ($class === null) {
            abort(404);
        }

        return $class::findOrFail($id);
    }
}
