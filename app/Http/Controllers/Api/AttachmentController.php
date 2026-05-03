<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\AttachmentResource;
use App\Models\Attachment;
use App\Models\Comment;
use App\Models\DiaryEntry;
use App\Models\EmergencyAssignment;
use App\Models\OnCallShift;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class AttachmentController extends Controller {
    private const MAX_BYTES = 25 * 1024 * 1024;
    private const ALLOWED_EXTENSIONS = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'pdf', 'txt', 'csv', 'log', 'zip', 'docx', 'xlsx'];
    private const TYPE_MAP = [
        'diary' => DiaryEntry::class,
        'comment' => Comment::class,
        'shift' => OnCallShift::class,
        'assignment' => EmergencyAssignment::class,
    ];

    public function store(Request $request, string $type, int $id): JsonResponse {
        Gate::authorize('create', Attachment::class);
        $class = self::TYPE_MAP[$type] ?? abort(404);
        $parent = $class::findOrFail($id);

        $request->validate(['file' => ['required', 'file', 'max:' . (self::MAX_BYTES / 1024)]]);
        $file = $request->file('file');
        $ext = strtolower($file->getClientOriginalExtension() ?: $file->extension());
        if (! in_array($ext, self::ALLOWED_EXTENSIONS, true)) {
            return response()->json(['message' => __('Dateityp nicht erlaubt.')], 422);
        }
        $path = $file->storeAs('attachments/' . now()->format('Y/m'), Str::uuid() . '.' . $ext, 'local');
        $att = $parent->attachments()->create([
            'user_id' => Auth::id(),
            'disk' => 'local',
            'path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'mime' => $file->getClientMimeType(),
            'size' => $file->getSize(),
        ]);
        return (new AttachmentResource($att->load('uploader:id,name')))->response()->setStatusCode(201);
    }

    public function download(Attachment $attachment): BinaryFileResponse {
        Gate::authorize('view', $attachment);
        $disk = Storage::disk($attachment->disk);
        abort_unless($disk->exists($attachment->path), 404);
        return response()->download($disk->path($attachment->path), $attachment->original_name);
    }

    public function destroy(Attachment $attachment): JsonResponse {
        Gate::authorize('delete', $attachment);
        Storage::disk($attachment->disk)->delete($attachment->path);
        $attachment->delete();
        return response()->json(['status' => 'deleted']);
    }
}
