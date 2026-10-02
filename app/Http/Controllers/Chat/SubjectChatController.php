<?php
/*
 * Created on   : Fri Oct 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SubjectChatController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Chat;

use App\Http\Controllers\Controller;
use App\Models\Diary\DiaryEntry;
use App\Models\Platform\User;
use App\Models\Project\Project;
use App\Services\Chat\SubjectChannelService;
use App\Support\Sqid;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\Gate;

/** Projekt- und Auftragschat öffnen (MVP-1061) — legt den Kanal beim ersten Mal an. */
class SubjectChatController extends Controller {
    private const SUBJECTS = ['project' => Project::class, 'diary' => DiaryEntry::class];

    public function open(Request $request, string $type, string $id, SubjectChannelService $channels): RedirectResponse {
        $class = self::SUBJECTS[$type] ?? null;
        abort_if($class === null, 404);
        $subject = $class::query()->findOrFail(Sqid::decodeOrNumeric($class, $id) ?? 0);
        Gate::authorize('view', $subject);
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return redirect()->route('chat.show', $channels->open($subject, $user));
    }
}
