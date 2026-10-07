<?php
/*
 * Created on   : Wed Oct 07 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : IntakeHandoverTarget.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Customer\Contracts;

use App\Enums\Customer\IntakeKind;
use App\Models\Customer\CustomerIntake;
use App\Models\Platform\{Organization, User};
use App\Services\Customer\Dto\IntakeStage;
use Illuminate\Database\Eloquent\Model;

/**
 * Erweiterungspunkt der Auftragsübernahme (MVP-1075): ein Modul legt aus
 * einem beauftragten Kundeneingang seinen vorhandenen Zielvorgang an
 * (Registrierung über `Manifest::extensions()`). Berechtigung, Annahme,
 * Sperre und Idempotenz prüft der {@see \App\Services\Customer\Intake\CustomerIntakeHandoverService}.
 */
interface IntakeHandoverTarget {
    public function kind(): IntakeKind;

    /** Modul-, Lizenz- oder Branchenfreigabe des Zielbereichs. */
    public function isAvailable(Organization $organization): bool;

    /** Erstell-/Änderungsrecht des Akteurs im Zielbereich. */
    public function canCreate(User $actor): bool;

    /** Blade-Partial der Zusatzfelder im Übernahmedialog, null = keine. */
    public function formView(): ?string;

    /** @return array<string, mixed> Daten für {@see formView()} */
    public function formData(CustomerIntake $intake): array;

    /** @return array<string, mixed> Validierungsregeln der Zusatzfelder */
    public function rules(): array;

    /**
     * Legt den Zielvorgang an; läuft in der Transaktion und unter der
     * Zeilensperre des Übernahmedienstes.
     *
     * @param  array<string, mixed>  $input  validierte Zusatzfelder
     */
    public function handOver(CustomerIntake $intake, User $actor, array $input): Model;

    /** Kundensicht aus dem tatsächlichen Stand des Zielvorgangs. */
    public function customerStage(Model $target): IntakeStage;

    /** Kurzbezeichnung des Ziels (Art und Nummer). */
    public function targetLabel(Model $target): string;

    /** Link in die Fachakte, null ohne Leserecht. */
    public function internalUrl(Model $target, User $viewer): ?string;

    /** Partial der internen Eingangsseite nach der Übernahme (`$intake`, `$target`), null = keins. */
    public function internalPanelView(): ?string;

    /** Partial der Portalseite nach der Übernahme (`$intake`, `$target`), null = keins. */
    public function portalPanelView(): ?string;
}
