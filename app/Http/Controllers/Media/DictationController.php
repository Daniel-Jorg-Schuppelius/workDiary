<?php
/*
 * Created on   : Fri Oct 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DictationController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Media;

use App\Http\Controllers\Concerns\ResolvesCurrentOrganization;
use App\Http\Controllers\Controller;
use App\Models\Media\Dictation;
use App\Models\Platform\User;
use App\Services\Media\DictationService;
use Illuminate\Http\{JsonResponse, Request};
use Illuminate\Validation\Rule;

/**
 * Sprachdiktat (MVP-1060): Aufnahme annehmen, Stand abfragen. Das Ergebnis
 * gehört der Person, die diktiert hat — niemand sonst liest es.
 */
class DictationController extends Controller {
    use ResolvesCurrentOrganization;

    public function __construct(private readonly DictationService $dictations) {}

    public function store(Request $request): JsonResponse {
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        abort_unless($this->dictations->isAvailable(), 503, (string) __('dictation.error.unavailable'));
        $data = $request->validate([
            'audio' => ['required', 'file', 'max:' . (int) config('media.transcription.dictation_max_kb', 20480), 'mimetypes:audio/webm,video/webm,audio/ogg,audio/mp4,audio/mpeg,audio/wav,audio/x-wav'],
            'context' => ['required', Rule::in(array_keys(Dictation::CONTEXTS))],
        ]);

        $dictation = $this->dictations->store($this->currentOrganization(), $user, $data['audio'], $data['context'], app()->getLocale());

        return response()->json(['id' => $dictation->sqid, 'status' => $dictation->status->value], 202);
    }

    public function show(Request $request, Dictation $dictation): JsonResponse {
        abort_unless($dictation->created_by === $request->user()?->id, 404);

        return response()->json([
            'status' => $dictation->status->value,
            'transcript' => $dictation->transcript,
            'fields' => $dictation->structured ?? [],
            'error' => $dictation->failure !== null ? (string) __('dictation.error.' . $dictation->failure) : null,
        ]);
    }
}
