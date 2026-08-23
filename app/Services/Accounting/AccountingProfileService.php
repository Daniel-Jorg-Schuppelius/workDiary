<?php
/*
 * Created on   : Fri Aug 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AccountingProfileService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Enums\Finance\{AccountingSovereignty, ProfitDetermination};
use App\Models\Accounting\{AccountingProfile, AccountingSovereigntyPeriod};
use App\Models\{Organization, User};
use App\Services\Accounting\Preflight\AccountingPreflightReport;
use Carbon\CarbonImmutable;
use CommonToolkit\Enums\CurrencyCode;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Einrichtung und Führungswechsel der lokalen Buchhaltung (Feature 125,
 * MVP-671).
 *
 * Einziger Schreibweg auf Profil und Hoheitsabschnitte. Zwei Regeln tragen
 * das ganze Modul:
 *
 *  1. Ohne bestandenen Preflight keine lokale Buchungshoheit.
 *  2. Jeder Wechsel schließt den laufenden Abschnitt taggenau und eröffnet
 *     den nächsten — es gibt keinen Zeitpunkt mit zwei Führenden und keinen
 *     ohne.
 */
class AccountingProfileService {
    public function __construct(
        private readonly AccountingSovereigntyResolver $resolver,
        private readonly AccountingSetupPreflight $preflight,
    ) {}

    /** Vorhandenes Profil oder ein unpersistierter Default (`preaccounting`). */
    public function profileFor(Organization $organization): AccountingProfile {
        $profile = $this->resolver->profile($organization);
        if ($profile instanceof AccountingProfile) {
            return $profile;
        }

        return new AccountingProfile([
            'organization_id' => $organization->id,
            'sovereignty' => AccountingSovereignty::Preaccounting,
            'profit_determination' => ProfitDetermination::Euer,
            'base_currency' => CurrencyCode::Euro,
            'fiscal_year_start_month' => 1,
        ]);
    }

    /**
     * Speichert die Einrichtungsangaben. Die Buchungshoheit bleibt dabei
     * unberührt — sie ändert sich nur über {@see self::activateLocal()} bzw.
     * {@see self::switchSovereignty()}, nie als Nebeneffekt eines Formulars.
     *
     * @param array{profit_determination: ProfitDetermination, base_currency: CurrencyCode, fiscal_year_start_month: int, starts_on: ?CarbonImmutable, note: ?string} $data
     */
    public function configure(Organization $organization, array $data): AccountingProfile {
        $profile = $this->profileFor($organization);

        if ($profile->exists && $profile->isLocalActive() && $data['starts_on']?->toDateString() !== $profile->starts_on?->toDateString()) {
            throw ValidationException::withMessages([
                'starts_on' => (string) __('accounting.ledger.error.start_locked'),
            ]);
        }

        $profile->fill([
            'organization_id' => $organization->id,
            'profit_determination' => $data['profit_determination'],
            'base_currency' => $data['base_currency'],
            'fiscal_year_start_month' => $data['fiscal_year_start_month'],
            'starts_on' => $data['starts_on']?->toDateString(),
            'note' => $data['note'],
        ]);
        $profile->save();

        return $profile->refresh();
    }

    /** Aktueller Prüfstand, ohne etwas zu verändern. */
    public function preflight(Organization $organization): AccountingPreflightReport {
        return $this->preflight->for($organization);
    }

    /**
     * Schaltet die lokale Buchhaltung ab dem Startdatum scharf.
     *
     * @throws ValidationException wenn der Preflight blockierende Punkte meldet
     */
    public function activateLocal(Organization $organization, User $actor): AccountingProfile {
        $profile = $this->resolver->profile($organization);
        if (! $profile instanceof AccountingProfile) {
            throw ValidationException::withMessages([
                'profile' => (string) __('accounting.ledger.preflight.profile_missing'),
            ]);
        }

        $report = $this->preflight->for($organization, $profile);
        if (! $report->isReady()) {
            throw ValidationException::withMessages([
                'preflight' => array_map(
                    fn ($check): string => $check->message,
                    $report->blockers(),
                ),
            ]);
        }

        $startsOn = CarbonImmutable::parse($profile->starts_on)->startOfDay();

        return DB::transaction(function () use ($organization, $profile, $actor, $startsOn, $report): AccountingProfile {
            $this->openSection($organization, AccountingSovereignty::Local, $startsOn, $actor, null, null);

            $profile->forceFill([
                'sovereignty' => AccountingSovereignty::Local,
                'external_provider' => null,
                'preflight' => $report->toArray(),
                'activated_at' => now(),
                'activated_by' => $actor->id,
            ])->save();

            $profile->audit('accounting.activated', [
                'starts_on' => $startsOn->toDateString(),
                'profit_determination' => $profile->profit_determination->value,
                'base_currency' => $profile->base_currency->value,
            ]);

            return $profile->refresh();
        });
    }

    /**
     * Wechselt die Buchungshoheit ab einem Stichtag (Rückgabe an ein externes
     * Hauptbuch oder zurück in die reine Belegvorstufe). Der eigentliche
     * Datenumzug bleibt Feature 110; hier wird nur die Führung umgehängt.
     */
    public function switchSovereignty(
        Organization $organization,
        AccountingSovereignty $target,
        CarbonImmutable $from,
        User $actor,
        ?string $provider = null,
        ?string $reason = null,
    ): AccountingProfile {
        if ($target === AccountingSovereignty::External && ($provider === null || $provider === '')) {
            throw ValidationException::withMessages([
                'external_provider' => (string) __('accounting.ledger.error.provider_required'),
            ]);
        }

        $current = $this->resolver->at($organization, $from);
        if ($current === $target && $target !== AccountingSovereignty::External) {
            throw ValidationException::withMessages([
                'sovereignty' => (string) __('accounting.ledger.error.sovereignty_unchanged'),
            ]);
        }

        // Der Weg in die lokale Führung geht IMMER durch den Preflight — sonst
        // wäre der Wechseldialog eine Hintertür an der Aktivierung vorbei.
        if ($target === AccountingSovereignty::Local) {
            $report = $this->preflight->for($organization);
            if (! $report->isReady()) {
                throw ValidationException::withMessages([
                    'preflight' => array_map(fn ($check): string => $check->message, $report->blockers()),
                ]);
            }
        }

        return DB::transaction(function () use ($organization, $target, $from, $actor, $provider, $reason): AccountingProfile {
            $this->openSection($organization, $target, $from->startOfDay(), $actor, $provider, $reason);

            $profile = $this->profileFor($organization);
            $profile->fill([
                'organization_id' => $organization->id,
                'sovereignty' => $target,
                'external_provider' => $target === AccountingSovereignty::External ? $provider : null,
            ]);
            $profile->save();

            $profile->audit('accounting.sovereignty_switched', [
                'to' => $target->value,
                'provider' => $provider,
                'from_date' => $from->toDateString(),
                'reason' => $reason,
            ]);

            return $profile->refresh();
        });
    }

    /**
     * Schließt den laufenden Abschnitt am Vortag und eröffnet den neuen.
     * Ein am selben Tag beginnender Abschnitt wird ersetzt (zweimaliges
     * Klicken darf keinen Doppeleintrag erzeugen); ein SPÄTER beginnender
     * blockiert, statt still überschrieben zu werden — die Reihenfolge der
     * Führungswechsel ist Nachweis, nicht Arbeitsstand.
     */
    private function openSection(
        Organization $organization,
        AccountingSovereignty $sovereignty,
        CarbonImmutable $from,
        User $actor,
        ?string $provider,
        ?string $reason,
    ): AccountingSovereigntyPeriod {
        $later = AccountingSovereigntyPeriod::query()
            ->where('organization_id', $organization->id)
            ->whereDate('valid_from', '>', $from->toDateString())
            ->orderBy('valid_from')
            ->first();

        if ($later instanceof AccountingSovereigntyPeriod) {
            throw ValidationException::withMessages([
                'valid_from' => (string) __('accounting.ledger.error.later_section_exists', [
                    'date' => $later->valid_from->format(\App\Support\Formats::date()),
                ]),
            ]);
        }

        AccountingSovereigntyPeriod::query()
            ->where('organization_id', $organization->id)
            ->whereDate('valid_from', '=', $from->toDateString())
            ->delete();

        AccountingSovereigntyPeriod::query()
            ->where('organization_id', $organization->id)
            ->whereNull('valid_to')
            ->whereDate('valid_from', '<', $from->toDateString())
            ->update(['valid_to' => $from->subDay()->toDateString()]);

        return AccountingSovereigntyPeriod::query()->create([
            'organization_id' => $organization->id,
            'sovereignty' => $sovereignty,
            'external_provider' => $sovereignty === AccountingSovereignty::External ? $provider : null,
            'valid_from' => $from->toDateString(),
            'valid_to' => null,
            'actor_user_id' => $actor->id,
            'reason' => $reason,
        ]);
    }
}
