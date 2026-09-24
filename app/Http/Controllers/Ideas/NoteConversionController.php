<?php
/*
 * Created on   : Thu Sep 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : NoteConversionController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Ideas;

use App\Http\Controllers\Controller;
use App\Models\Communication\CommunicationNote;
use App\Models\Knowledge\KnowledgeArticle;
use App\Models\Platform\User;
use App\Services\Ideas\NodeConversionService;
use App\Support\{ErrorText, Sqid};
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\{Auth, Gate};

/** Notiz → Wissensartikel-Entwurf mit Herkunftsverweis (MVP-813); Route `communication-notes.convert-knowledge`. */
class NoteConversionController extends Controller {
    public function __invoke(CommunicationNote $note, NodeConversionService $conversions): RedirectResponse {
        Gate::authorize('view', $note);
        /** @var User $actor */
        $actor = Auth::user();
        abort_if($note->isPrivate() && (int) $note->created_by_user_id !== (int) $actor->id, 404);

        try {
            $reference = $conversions->convertNoteToKnowledgeArticle($note, $actor);
        } catch (\RuntimeException $e) {
            return redirect()->back()->with('error', ErrorText::for($e));
        }

        return redirect()
            ->route('knowledge.show', Sqid::encode(KnowledgeArticle::class, $reference->target_id))
            ->with($reference->wasRecentlyCreated ? 'success' : 'info', (string) __($reference->wasRecentlyCreated ? 'communication.convert.flash.created' : 'communication.convert.flash.existing'));
    }
}
