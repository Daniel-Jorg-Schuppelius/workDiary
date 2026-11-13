<?php
/*
 * Created on   : Fri Nov 13 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AppWishesSection.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Privacy\SubjectData;

use App\Models\Platform\User;
use App\Models\Wishes\{AppWish, AppWishVote};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Wünsche an die App in der Betroffenenauskunft (Feature 206 × Feature 129):
 * eigene Wünsche mit Art, Stand, Text und Antwort sowie die abgegebenen
 * Stimmen — je Stimme Art und Text des Wunsches, nie wer ihn geäußert hat.
 */
class AppWishesSection extends AbstractSubjectSection {
    /** Länge des Wunschtextes in der Stimmenliste. */
    private const int VOTE_EXCERPT = 160;

    public function key(): string {
        return 'wishes';
    }

    public function title(): string {
        return __('wishes.privacy.title');
    }

    public function portable(): bool {
        return false;
    }

    public function build(Model $subject): array {
        $this->expect($subject, User::class);
        /** @var User $u */
        $u = $subject;
        $orgId = (int) $u->organization_id;

        $wishes = AppWish::query()->withoutGlobalScopes()
            ->where('organization_id', $orgId)->where('created_by', $u->id);
        $votes = AppWishVote::query()->withoutGlobalScopes()
            ->where('organization_id', $orgId)->where('user_id', $u->id);

        $voteList = [];
        foreach ((clone $votes)->with(['wish' => static fn ($query) => $query->withoutGlobalScopes()])->orderBy('created_at')->orderBy('id')->get() as $vote) {
            $voteList[] = [
                'voted_at' => $this->str($vote->created_at),
                'kind' => $this->str($vote->wish?->kind),
                'wish' => $vote->wish !== null ? Str::limit($vote->wish->body, self::VOTE_EXCERPT) : null,
            ];
        }

        return [
            'lists' => [__('wishes.privacy.votes') => $voteList],
            'families' => [
                $this->family(
                    'app_wishes',
                    __('wishes.privacy.wishes'),
                    $wishes,
                    'created_at',
                    columns: [
                        'created_at' => __('wishes.field.created_at'),
                        'kind' => __('wishes.field.kind'),
                        'status' => __('wishes.field.status'),
                        'body' => __('wishes.field.body'),
                        'answer' => __('wishes.field.answer'),
                    ],
                ),
                $this->family('app_wish_votes', __('wishes.privacy.votes'), $votes, 'created_at'),
            ],
        ];
    }
}
