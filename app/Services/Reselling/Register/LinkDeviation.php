<?php
/*
 * Created on   : Fri Nov 20 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LinkDeviation.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Reselling\Register;

use App\Enums\Reselling\BillingFrequency;
use App\Models\Reselling\{ResalePeriod, ResaleSubscription};
use App\Services\Reselling\Mirror\MirrorLine;

/**
 * Weicht ein Rechnungsbezug vom Abo ab (Phase 187, MVP-1768)? Zwei Arten:
 * anderer Artikel (beide Seiten kennen ihren Katalogartikel, und er ist
 * verschieden) und anderer Betrag (Positionspreis der Monate gegen das Soll
 * der Periode, über der Toleranz). Namensgleichheit ohne Artikel wird nicht
 * bewertet — sie ist die Sache des Vorschlagslaufs.
 */
final class LinkDeviation {
    public const string ARTICLE = 'article';

    public const string AMOUNT = 'amount';

    /** Toleranz des Betrags: Anteil am Soll, mindestens {@see TOLERANCE_MIN} (Entscheidung des Inhabers 2026-11-20). */
    public const float TOLERANCE = 0.05;

    public const float TOLERANCE_MIN = 1.0;

    /** Art der Abweichung eines Bezugs über `$months` Lizenzmonate, null wenn er passt. */
    public static function of(ResalePeriod $period, MirrorLine $line, float $months): ?string {
        if ($line->isCreditNote) {
            return null;
        }
        if (self::articleDiffers($period->subscription, $line)) {
            return self::ARTICLE;
        }
        $expected = self::comparable($period) ? $period->amountForMonths(abs($months)) : null;
        if ($expected === null) {
            return null;
        }
        $actual = $line->unitNet->times(LicenseMonths::unitsFor($line, abs($months), $period->termMonths()))->withScale(2);

        return self::amountDiffers($expected->toFloat(), $actual->toFloat()) ? self::AMOUNT : null;
    }

    /**
     * Lässt sich der Betrag vergleichen? Eine Rumpfperiode eines Jahresabos trägt das
     * Soll des ganzen Jahres auf wenige Monate — ihr Monatspreis sagt nichts.
     */
    public static function comparable(ResalePeriod $period): bool {
        return $period->subscription->interval !== BillingFrequency::Yearly || $period->termMonths() >= 12;
    }

    public static function articleDiffers(ResaleSubscription $subscription, MirrorLine $line): bool {
        return $subscription->article_ref !== null && $line->articleKey !== null && $subscription->article_ref !== $line->articleKey;
    }

    public static function amountDiffers(float $expected, float $actual): bool {
        return abs($expected - $actual) > max(self::TOLERANCE_MIN, abs($expected) * self::TOLERANCE);
    }
}
