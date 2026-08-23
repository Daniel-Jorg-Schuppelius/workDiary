<?php
/*
 * Created on   : Fri Aug 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AccountingSovereigntyResolver.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Enums\Finance\AccountingSovereignty;
use App\Models\Accounting\{AccountingProfile, AccountingSovereigntyPeriod};
use App\Models\Organization;
use Carbon\{CarbonImmutable, CarbonInterface};

/**
 * Stichtags-Guard der Buchungshoheit (Feature 125, MVP-671).
 *
 * Einziger Prüfpunkt für die Frage „darf für diesen Tag lokal gebucht
 * werden?". Die Antwort kommt aus den Hoheitsabschnitten, nicht aus dem
 * aktuellen Profilzustand — sonst würde ein späterer Wechsel rückwirkend
 * umschreiben, wer im März geführt hat.
 *
 * Analog zur {@see \App\Services\AccountingMigration\CutoverGuard} auf der
 * Fakturaachse: ohne gesetzten Stichtag bleibt alles wie bisher.
 */
class AccountingSovereigntyResolver {
    /** Wer führte das Hauptbuch an diesem Tag? */
    public function at(Organization $organization, ?CarbonInterface $date = null): AccountingSovereignty {
        $period = $this->periodAt($organization, $date);

        return $period instanceof AccountingSovereigntyPeriod
            ? $period->sovereignty
            : AccountingSovereignty::Preaccounting;
    }

    /** Der abdeckende Hoheitsabschnitt, falls vorhanden. */
    public function periodAt(Organization $organization, ?CarbonInterface $date = null): ?AccountingSovereigntyPeriod {
        $day = CarbonImmutable::parse($date ?? now())->startOfDay();

        return AccountingSovereigntyPeriod::query()
            ->where('organization_id', $organization->id)
            ->whereDate('valid_from', '<=', $day->toDateString())
            ->where(function ($query) use ($day): void {
                $query->whereNull('valid_to')->orWhereDate('valid_to', '>=', $day->toDateString());
            })
            ->orderByDesc('valid_from')
            ->first();
    }

    /** Darf für diesen Tag lokal festgeschrieben werden? */
    public function allowsLocalPosting(Organization $organization, ?CarbonInterface $date = null): bool {
        return $this->at($organization, $date)->allowsLocalPosting();
    }

    /**
     * Guard für alle künftigen Schreibpfade des Buchungskerns (MVP-672 ff.).
     *
     * @throws AccountingSovereigntyException
     */
    public function assertLocalPostingAllowed(Organization $organization, ?CarbonInterface $date = null): void {
        $day = CarbonImmutable::parse($date ?? now())->startOfDay();
        $period = $this->periodAt($organization, $day);
        $sovereignty = $period instanceof AccountingSovereigntyPeriod
            ? $period->sovereignty
            : AccountingSovereignty::Preaccounting;

        if (! $sovereignty->allowsLocalPosting()) {
            throw new AccountingSovereigntyException($sovereignty, $day, $period?->external_provider);
        }
    }

    /** Profil der Organisation, sofern eingerichtet. */
    public function profile(Organization $organization): ?AccountingProfile {
        return AccountingProfile::query()->where('organization_id', $organization->id)->first();
    }
}
