<?php
/*
 * Created on   : Mon Aug 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DataSubjectKind.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Privacy;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;
use App\Models\Applications\JobApplication;
use App\Models\Club\ClubMember;
use App\Models\Customer\Customer;
use App\Models\Platform\User;
use App\Models\Sales\Lead;
use App\Models\Supplier\Supplier;

/**
 * Betroffenenart einer Auskunft (Art. 15/20 DSGVO, Feature 129): bestimmt,
 * aus welchen Fachtabellen der {@see \App\Services\Privacy\SubjectDataExporter}
 * die personenbezogenen Daten zieht. Portal-Nutzer sind User-Zeilen mit
 * Kundenbindung (`customer_id`) — eigene Art, weil ihre Datenfamilien
 * (kein HR-Stamm, keine Arbeitszeiten) andere sind.
 */
enum DataSubjectKind: string implements HasLabel {
    use HasOptions;

    case User = 'user';                       // Mitarbeiter
    case PortalUser = 'portal_user';          // Kundenportal-Konto (users.customer_id)
    case Customer = 'customer';
    case Supplier = 'supplier';
    case Lead = 'lead';
    case JobApplication = 'job_application';  // Bewerber
    case ClubMember = 'club_member';          // Vereinsmitglied (MVP-1009)

    public function label(): string {
        return match ($this) {
            self::User => __('enums.privacy.data_subject_kind.user'),
            self::PortalUser => __('enums.privacy.data_subject_kind.portal_user'),
            self::Customer => __('enums.privacy.data_subject_kind.customer'),
            self::Supplier => __('enums.privacy.data_subject_kind.supplier'),
            self::Lead => __('enums.privacy.data_subject_kind.lead'),
            self::JobApplication => __('enums.privacy.data_subject_kind.job_application'),
            self::ClubMember => __('enums.privacy.data_subject_kind.club_member'),
        };
    }

    /** @return class-string<\Illuminate\Database\Eloquent\Model> */
    public function modelClass(): string {
        return match ($this) {
            self::User, self::PortalUser => User::class,
            self::Customer => Customer::class,
            self::Supplier => Supplier::class,
            self::Lead => Lead::class,
            self::JobApplication => JobApplication::class,
            self::ClubMember => ClubMember::class,
        };
    }
}
