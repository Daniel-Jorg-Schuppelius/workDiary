<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : WishesDemoBlock.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Wishes\Demo;

use App\Enums\Wishes\{AppWishKind, AppWishStatus};
use App\Models\Platform\Organization;
use App\Models\Scopes\OrganizationScope;
use App\Models\Wishes\{AppWish, AppWishVote};
use App\Services\Demo\Contracts\{DemoBlock, DemoSeedContext};

/**
 * Wünsche zur Vorführung (Feature 206, MVP-1658/1659): drei erfundene
 * Wünsche mit Stimmen, einer beantwortet, einer an den Plattformbetrieb
 * weitergegeben. Direkt geschrieben statt über den Dienst, damit beim
 * Anlegen der Demo keine Hinweise verschickt werden. Nur Demo-Personen.
 */
final class WishesDemoBlock implements DemoBlock {
    public function supports(DemoSeedContext $context): bool {
        return $context->users->count() >= 2;
    }

    public function seed(DemoSeedContext $context): array {
        $organizationId = (int) $context->organization->id;
        $people = $context->users->values()->all();
        if (count($people) < 2) {
            return [];
        }
        // Art, Text, Seite, Stand, Antwort der Organisation; der dritte Wunsch ist an den Betrieb weitergegeben.
        $samples = [
            [AppWishKind::Wish, (string) __('wishes.demo.wish_filter'), 'work-queue.index', AppWishStatus::Planned, (string) __('wishes.demo.answer_filter')],
            [AppWishKind::Question, (string) __('wishes.demo.question_export'), 'today.show', AppWishStatus::New, null],
            [AppWishKind::Bug, (string) __('wishes.demo.bug_date'), 'week.index', AppWishStatus::InReview, null],
        ];

        $wishes = 0;
        $votes = 0;
        foreach ($samples as $index => [$kind, $body, $route, $status, $answer]) {
            $author = $people[$index % count($people)];
            $forward = $index === 2;
            $wish = AppWish::query()->withoutGlobalScope(OrganizationScope::class)->create([
                'organization_id' => $organizationId,
                'kind' => $kind,
                'body' => $body,
                'route_name' => $route,
                'status' => $status,
                'answer' => $answer,
                'answered_at' => $answer !== null ? now() : null,
                'answered_by' => $answer !== null ? (int) $context->actor->id : null,
                'app_version' => (string) config('app.version'),
                'created_by' => (int) $author->id,
                'forwarded_at' => $forward ? now() : null,
                'forwarded_body' => $forward ? $body : null,
            ]);
            $wishes++;
            foreach ($people as $voter) {
                if ((int) $voter->id === (int) $author->id) {
                    continue;
                }
                AppWishVote::query()->withoutGlobalScope(OrganizationScope::class)->create([
                    'organization_id' => $organizationId,
                    'app_wish_id' => $wish->id,
                    'user_id' => $voter->id,
                ]);
                $votes++;
            }
        }

        return ['app_wishes' => $wishes, 'app_wish_votes' => $votes];
    }

    public function purge(Organization $organization): void {
        // Stimmen löscht die Fremdschlüssel-Kaskade mit.
        AppWish::query()->withoutGlobalScope(OrganizationScope::class)->where('organization_id', $organization->id)->update(['platform_bundle_id' => null]);
        AppWish::query()->withoutGlobalScope(OrganizationScope::class)->where('organization_id', $organization->id)->delete();
    }
}
