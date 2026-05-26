<?php
/*
 * Created on   : Sun May 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : HelpTopicResolver.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Services\Help;

use App\Models\{HelpTopic, User};
use Illuminate\Support\Facades\App;

class HelpTopicResolver {
    /**
     * Findet ein Topic für die bevorzugte Locale, mit Fallback de→en.
     * Audience-Filter: leere/fehlende audience = sichtbar für alle.
     */
    public function find(string $topic, ?User $user = null, ?string $preferredLocale = null): ?HelpTopic {
        $locales = $this->localeFallbackChain($preferredLocale);

        $candidates = HelpTopic::query()
            ->where('topic', $topic)
            ->whereIn('locale', $locales)
            ->get()
            ->keyBy('locale');

        foreach ($locales as $locale) {
            /** @var HelpTopic|null $row */
            $row = $candidates->get($locale);
            if ($row !== null && $this->isVisibleFor($row, $user)) {
                return $row;
            }
        }

        return null;
    }

    /**
     * Volltextsuche auf body_md + title, eingeschränkt auf zugelassene Audiences.
     *
     * @return \Illuminate\Support\Collection<int, HelpTopic>
     */
    public function search(string $query, ?User $user = null, ?string $preferredLocale = null, int $limit = 20): \Illuminate\Support\Collection {
        $query = trim($query);
        if ($query === '') {
            return collect();
        }

        $locale = $this->localeFallbackChain($preferredLocale)[0];

        $rows = HelpTopic::query()
            ->where('locale', $locale)
            ->where(function ($q) use ($query): void {
                $like = '%' . $query . '%';
                $q->where('title', 'like', $like)
                    ->orWhere('body_md', 'like', $like)
                    ->orWhere('topic', 'like', $like);
            })
            ->limit($limit * 3)
            ->get();

        return $rows
            ->filter(fn(HelpTopic $row) => $this->isVisibleFor($row, $user))
            ->values()
            ->take($limit);
    }

    public function isVisibleFor(HelpTopic $topic, ?User $user): bool {
        $audience = $topic->audience;
        if (! is_array($audience) || $audience === []) {
            return true;
        }
        if (in_array('*', $audience, true)) {
            return true;
        }
        if ($user === null) {
            return false;
        }

        foreach ($audience as $code) {
            if ($code === '') {
                continue;
            }
            if ($user->hasRole($code)) {
                return true;
            }
        }

        return false;
    }

    /** @return list<string> */
    private function localeFallbackChain(?string $preferredLocale): array {
        $primary = $preferredLocale ?? App::getLocale();
        $chain = [$primary];
        if ($primary !== 'de') {
            $chain[] = 'de';
        }
        if (! in_array('en', $chain, true)) {
            $chain[] = 'en';
        }

        return $chain;
    }
}
