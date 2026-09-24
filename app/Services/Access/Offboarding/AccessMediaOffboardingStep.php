<?php
/*
 * Created on   : Thu Sep 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AccessMediaOffboardingStep.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Access\Offboarding;

use App\Models\Platform\User;
use App\Services\Access\AccessMediumService;
use App\Services\Org\Contracts\OffboardingStep;

/** Wer geht, gibt erst ab (Feature 092): ausgegebene Zutrittsmedien sperren Austritt und Entfernen. */
final class AccessMediaOffboardingStep implements OffboardingStep {
    public function __construct(private readonly AccessMediumService $media) {}

    public function blockers(User $member): array {
        $open = $this->media->openMediaFor($member);
        if ($open->isEmpty()) {
            return [];
        }

        return [(string) __(':name hält noch :count Zutrittsmedien (:list) — erst zurücknehmen, dann entfernen.', [
            'name' => $member->name,
            'count' => $open->count(),
            'list' => $open->map(fn ($medium) => ($medium->label ?: __('Medium')) . ' …' . $medium->number_suffix)->implode(', '),
        ])];
    }

    public function onExit(User $member): void {}
}
