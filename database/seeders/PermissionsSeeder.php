<?php
/*
 * Created on   : Thu May 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PermissionsSeeder.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Database\Seeders;

use App\Enums\User\{Permission as PermissionEnum, UserRole};
use App\Models\Organization;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\{Permission, Role};
use Spatie\Permission\PermissionRegistrar;

/**
 * Legt alle in {@see PermissionEnum} definierten Permissions als globale
 * (team-unabhängige) Spatie-Permissions an und stellt für jede bestehende
 * Organisation die Default-Rollen mit sinnvollen Permission-Zuordnungen
 * bereit. Idempotent — kann beliebig oft ausgeführt werden.
 *
 * Rollen sind organisationsspezifisch (team_id = organization.id) und
 * werden ausschließlich hier definiert. Nutzer-Zuweisungen erfolgen über
 * die Admin-UI (Admin\Access\MemberController).
 */
class PermissionsSeeder extends Seeder {
    public function run(): void {
        /** @var PermissionRegistrar $registrar */
        $registrar = app(PermissionRegistrar::class);
        $registrar->forgetCachedPermissions();

        self::ensurePermissionsExist();

        // Globaler Plattform-Admin (team_id = null): erhält alle Permissions.
        // Der Plattform-Admin überspringt zusätzlich alle Policies via
        // HasAdminBypass-Trait; die Permission-Zuordnung dient hier nur der
        // Transparenz in der Admin-UI.
        $globalAdmin = Role::query()
            ->whereNull(config('permission.column_names.team_foreign_key', 'team_id'))
            ->where('name', UserRole::Admin->value)
            ->where('guard_name', 'web')
            ->first();

        if (! $globalAdmin instanceof Role) {
            $registrar->setPermissionsTeamId(null);
            /** @var Role $globalAdmin */
            $globalAdmin = Role::findOrCreate(UserRole::Admin->value, 'web');
        }

        // Bewusst nur die in der zentralen PermissionEnum definierten Rechte:
        // modul-eigene Permissions (z. B. whistleblowing.*) sollen NICHT
        // automatisch an den Plattform-Admin gehen (Abschnitt 5/25 des
        // Hinweisgeber-Konzepts – getrennte Meldestelle).
        $enumNames = array_map(static fn(PermissionEnum $p): string => $p->value, PermissionEnum::cases());
        $globalAdmin->syncPermissions(
            Permission::query()->where('guard_name', 'web')->whereIn('name', $enumNames)->get()
        );

        // Pro bestehender Organisation die vier Default-Rollen anlegen.
        foreach (Organization::query()->orderBy('id')->get() as $organization) {
            $this->seedOrganization($organization, $registrar);
        }
    }

    /**
     * Legt die Default-Rollen einer einzelnen Organisation an. Wird zentral
     * vom Observer (siehe OrganizationObserver) und vom Seeder aufgerufen.
     */
    public static function seedOrganization(Organization $organization, ?PermissionRegistrar $registrar = null): void {
        $registrar ??= app(PermissionRegistrar::class);
        $registrar->setPermissionsTeamId($organization->id);

        // Defense in Depth: bei Org-Creates über den OrganizationObserver
        // (z. B. in Tests, Tenant-Registrierung, frischer DB) wurde der
        // Haupt-PermissionsSeeder unter Umständen noch nicht ausgeführt
        // und die referenzierten Spatie-Permissions existieren noch nicht.
        // Ohne diese Sicherung würde syncPermissions() unten in eine
        // PermissionDoesNotExist-Exception laufen und Org-Anlage scheitern.
        self::ensurePermissionsExist();

        $teamForeign = config('permission.column_names.team_foreign_key', 'team_id');
        $rolesAndPermissions = self::defaultRoleMatrix();

        foreach ($rolesAndPermissions as $roleName => $permissions) {
            // Spatie's Role::findOrCreate würde auf eine bestehende globale
            // Rolle (team_id = NULL) zurückfallen. Wir wollen pro Organisation
            // eine eigene Rollen-Instanz und müssen daher direkt mit
            // firstOrCreate gegen das volle Attribute-Set arbeiten.
            /** @var Role $role */
            $role = Role::query()->firstOrCreate([
                $teamForeign => $organization->id,
                'name' => $roleName,
                'guard_name' => 'web',
            ]);

            $role->syncPermissions(
                array_map(static fn(PermissionEnum $p): string => $p->value, $permissions)
            );
        }
    }

    /**
     * Stellt sicher, dass alle in {@see PermissionEnum} definierten
     * Permissions auf dem 'web'-Guard existieren. Idempotent. Wird vom
     * Haupt-`run()` und vom Observer-Pfad gleichermaßen verwendet, damit
     * Org-Erstellungen vor dem ersten Permissions-Seeding nicht in eine
     * `PermissionDoesNotExist`-Exception laufen.
     *
     * Fast-Path: Es werden nur die tatsächlich FEHLENDEN Permissions angelegt
     * (Abgleich über die Namen, EINE whereIn-Query). Ein reiner Count-Vergleich
     * wäre falsch — bei Permission-Änderungen (eine neue hinzu, eine alte weg)
     * kann die Anzahl gleich bleiben, während ein neuer Name (z. B.
     * `article.view`) fehlt und das spätere syncPermissions() in eine
     * PermissionDoesNotExist-Exception liefe. Bleibt für Tests schnell, weil im
     * Normalfall nichts fehlt und keine ~138 findOrCreate-Queries laufen.
     */
    private static function ensurePermissionsExist(): void {
        $enumValues = array_map(static fn(PermissionEnum $p): string => $p->value, PermissionEnum::cases());

        $existing = Permission::query()
            ->where('guard_name', 'web')
            ->whereIn('name', $enumValues)
            ->pluck('name')
            ->all();

        $missing = array_diff($enumValues, $existing);
        if ($missing === []) {
            return;
        }

        foreach ($missing as $name) {
            Permission::findOrCreate($name, 'web');
        }
    }

    /**
     * Default-Mapping Rolle → Permissions. Die Plattform-Admin-Rolle wird
     * zusätzlich global (team_id = null) angelegt; hier deckt der Eintrag
     * den Org-Admin ab, der innerhalb seiner Organisation alles darf.
     *
     * Profile gemäß Feature 019 / MVP-003:
     * - admin (Kundenadmin): alles innerhalb der Org
     * - geschaeftsfuehrung: read-only über alle Bereiche + Reports + Audit
     * - teamleitung: Mitarbeiter-, Zeit- und Planungsführung, ohne Finanzen
     * - buchhaltung: Kunden, Rechnungen, Stundenzettel, Auswertungen
     * - user (Innendienst): Standard-Mitarbeiter (eigene Zeit, Diary, Urlaub)
     * - aussendienst: mobile Erfassung (eigene Zeit, Diary, Touren, Spesen)
     * - callcenter: Tagebuch und Kundendaten ansehen
     * - support: Anbieter-Support, ausschließlich read-only + Audit
     *
     * @return array<string, list<PermissionEnum>>
     */
    private static function defaultRoleMatrix(): array {
        $all = PermissionEnum::cases();

        // Geschäftsführung: read-only über alle Bereiche, Reports + Audit.
        $geschaeftsfuehrung = array_values(array_filter(
            PermissionEnum::cases(),
            static function (PermissionEnum $p): bool {
                $value = $p->value;
                // Betriebsmetriken (Feature 036) sind bewusst NUR Admin —
                // die .view-Heuristik unten würde metrics.view sonst mitnehmen.
                if (str_starts_with($value, 'metrics.')) {
                    return false;
                }
                // Admin-Sicherheitsübersicht (Feature 016) ist ebenfalls NUR
                // Admin — security.view würde sonst über die .view-Heuristik an
                // die Geschäftsführung gehen.
                if (str_starts_with($value, 'security.')) {
                    return false;
                }
                if (str_ends_with($value, '.viewAny') || str_ends_with($value, '.view') || str_ends_with($value, '.viewOwn')) {
                    return true;
                }

                return in_array($value, [
                    PermissionEnum::OrganizationView->value,
                    PermissionEnum::ReportView->value,
                    PermissionEnum::ReportExport->value,
                    // Feature 002: Zielwert-Pflege ist GF-/Admin-Sache; die
                    // .view-Heuristik trifft `report.target.manage` nicht.
                    PermissionEnum::ReportTargetManage->value,
                    PermissionEnum::AuditLogView->value,
                    PermissionEnum::AccessAuditView->value,
                    PermissionEnum::FlexBalanceView->value,
                    PermissionEnum::ClassificationList->value,
                    // Geschäftsführung darf — wie die Personalverwaltung —
                    // Personal-/Lohndaten und das Arbeitszeit-Modell pflegen.
                    PermissionEnum::UserPayrollManage->value,
                    PermissionEnum::WorkScheduleManage->value,
                    // MVP-005: Datenschutzbericht als PDF/Export — die
                    // .view-Heuristik trifft hier nicht, daher explizit.
                    PermissionEnum::PrivacyReportExport->value,
                    // MVP-016/017: Monats-/Korrektur-Lesezugriff — die
                    // .view-Heuristik trifft auf `month.view.*`/`correction.view.*`
                    // nicht, daher explizit.
                    PermissionEnum::MonthViewOrganization->value,
                    PermissionEnum::CorrectionViewOrganization->value,
                    // MVP-015: Tagesabschluss-Lesezugriff auf Org-Ebene —
                    // analog zu month.view.organization explizit.
                    PermissionEnum::DayCloseViewOrganization->value,
                    // Rang 38: Plan/Ist-Anwesenheit org-weit — die .view-
                    // Heuristik trifft `report.presence.organization` nicht.
                    PermissionEnum::ReportPresenceOrganization->value,
                    // Feature 068: Go-/No-go- und Gewinn-/Zusage-Entscheidungen
                    // sind GF-Sache; die .view-Heuristik trifft *.decide nicht.
                    PermissionEnum::TenderDecide->value,
                    PermissionEnum::RecruitingDecide->value,
                    // Feature 069: Investitionsfreigaben (Schwellenwerte) sind GF-Sache.
                    PermissionEnum::InvestmentApprove->value,
                    PermissionEnum::InvestmentManage->value,
                    // Feature 070: Krisenstab führt die Geschäftsleitung.
                    PermissionEnum::CrisisManage->value,
                    PermissionEnum::CrisisApprove->value,
                    // Feature 071: Nachhaltigkeitssteuerung ist Leitungsaufgabe.
                    PermissionEnum::SustainabilityManage->value,
                    // Feature 072: Anspruchs-/Kulanzentscheidung ist GF-Sache;
                    // die .view-Heuristik trifft claim.decide nicht.
                    PermissionEnum::ClaimDecide->value,
                    // Feature 074: vertrauliche Leasingkonditionen (Raten/
                    // Restwerte/Optionen) sind Leitungssache.
                    PermissionEnum::AssetFinanceFinance->value,
                    // Feature 075/D12: befristete Ausnahmefreigaben für
                    // gesperrte Assets entscheidet die Leitung.
                    PermissionEnum::AssetComplianceRelease->value,
                    PermissionEnum::AssetBlockOverride->value,
                ], true);
            }
        ));

        // Teamleitung: operative Führung (Personal, Zeit, Plan), ohne Finanzen.
        $teamleitung = [
            PermissionEnum::OrganizationView,
            PermissionEnum::NumberFormatManage,
            // Feature 068: Ausschreibungsakten operativ führen (Unterlagen,
            // Fristen, Einreichung) — Go-/No-go und Zuschlag bleiben GF/Admin.
            PermissionEnum::TenderViewAny,
            PermissionEnum::TenderView,
            PermissionEnum::TenderManage,
            // Feature 069: Fachverantwortliche pflegen Akten/Varianten —
            // Budgetfreigabe bleibt GF/Buchhaltung/Admin.
            PermissionEnum::InvestmentViewAny,
            PermissionEnum::InvestmentView,
            PermissionEnum::InvestmentManage,
            // Feature 070: Teamleitung sieht Krisenlagen (Lagebild), führt
            // aber nicht den Stab (manage/approve bleibt GF/Admin).
            PermissionEnum::CrisisViewAny,
            PermissionEnum::CrisisView,
            // Feature 071: Fachleitungen bewerten Geräte/Prozesse und pflegen
            // Aktivitätsdaten + Maßnahmen.
            PermissionEnum::SustainabilityViewAny,
            PermissionEnum::SustainabilityView,
            PermissionEnum::SustainabilityManage,
            // Feature 072: Reklamationsakten operativ führen inkl. Rückläufer
            // und Lieferantenregress — Entscheidung (decide) bleibt GF/Admin,
            // kaufmännische Freigabe (finance) bei der Buchhaltung.
            PermissionEnum::ClaimViewAny,
            PermissionEnum::ClaimView,
            PermissionEnum::ClaimManage,
            PermissionEnum::ClaimWarehouse,
            PermissionEnum::ClaimRecourse,
            // Feature 073: Verleih operativ führen (Akten, Reservierung,
            // Übergabe/Rücknahme, Preislisten) — kaufmännische Freigabe
            // (finance) bleibt bei der Buchhaltung.
            PermissionEnum::RentalViewAny,
            PermissionEnum::RentalView,
            PermissionEnum::RentalManage,
            PermissionEnum::RentalHandover,
            PermissionEnum::RentalRates,
            // Feature 100: Entsorgungsakten operativ führen (Geräteliste,
            // Behandlung, Übergabe) inkl. bewachtem Abschluss — die Gates
            // (Unterschrift, Behandlungs-/Nachweispflicht) sichern den Rest.
            PermissionEnum::DisposalViewAny,
            PermissionEnum::DisposalView,
            PermissionEnum::DisposalManage,
            PermissionEnum::DisposalComplete,
            // Welle D (CLM): allgemeine Vertragsakten operativ führen inkl.
            // Vertragskalender (Obligationen).
            PermissionEnum::ContractViewAny,
            PermissionEnum::ContractView,
            PermissionEnum::ContractManage,
            // D12: Sperren setzen/aufheben ist Leitungsaufgabe — die
            // Ausnahmefreigabe (override) bleibt GF/Admin.
            PermissionEnum::AssetBlockManage,
            // Feature 075: Prüfprofile/-pflichten pflegen und Prüfungen
            // erfassen — Ausnahmefreigaben (release) bleiben GF/Admin.
            PermissionEnum::AssetComplianceViewAny,
            PermissionEnum::AssetComplianceView,
            PermissionEnum::AssetComplianceManage,
            PermissionEnum::AssetComplianceInspect,
            // Genehmigungs-Register (Veranstalter): operative Pflege.
            PermissionEnum::PermitViewAny,
            PermissionEnum::PermitView,
            PermissionEnum::PermitCreate,
            PermissionEnum::PermitUpdate,
            PermissionEnum::PermitDelete,
            PermissionEnum::UserViewAny,
            PermissionEnum::UserView,
            PermissionEnum::UserFlexManage,
            PermissionEnum::CustomerViewAny,
            PermissionEnum::CustomerView,
            PermissionEnum::ProjectViewAny,
            PermissionEnum::ProjectView,
            PermissionEnum::ProjectCreate,
            PermissionEnum::ProjectUpdate,
            PermissionEnum::ProjectArchive,
            PermissionEnum::TaskManage,
            PermissionEnum::MilestoneManage,
            PermissionEnum::TimeEntryViewAny,
            PermissionEnum::TimeEntryApprove,
            PermissionEnum::TimeEntryCreateForOthers,
            PermissionEnum::TimesheetViewAny,
            PermissionEnum::TimesheetSign,
            PermissionEnum::TimesheetLock,
            PermissionEnum::TimesheetUnlock,
            PermissionEnum::MonthViewTeam,
            PermissionEnum::MonthApprove,
            PermissionEnum::MonthReject,
            PermissionEnum::MonthReopen,
            PermissionEnum::MonthLock,
            PermissionEnum::CorrectionViewTeam,
            PermissionEnum::CorrectionApprove,
            PermissionEnum::CorrectionReject,
            PermissionEnum::CorrectionCreateForOthers,
            // Tagesabschluss (MVP-015): Team-Sicht + Korrektur-Entscheidung;
            // dayClose.reopen bleibt bewusst Admin-exklusiv (§7).
            PermissionEnum::DayCloseViewTeam,
            PermissionEnum::DayCloseApproveCorrection,
            PermissionEnum::ExportTimeCreate,
            PermissionEnum::ExportTimeDeliver,
            PermissionEnum::ExportTimeDelete,
            PermissionEnum::DiaryViewAny,
            PermissionEnum::DiaryCreate,
            PermissionEnum::DiaryCreateForOthers,
            PermissionEnum::DiaryUpdate,
            PermissionEnum::DiaryExport,
            PermissionEnum::OrderAccept,
            PermissionEnum::OrderWork,
            PermissionEnum::OrderComplete,
            PermissionEnum::OrderHandover,
            PermissionEnum::OrderCancel,
            // Disposition / Einsatzplanung (Feature 028): Teamleitung disponiert
            // Aufträge (Konfliktwarnungen, Status, Fahrzeug-Reservierung).
            PermissionEnum::DispatchViewAny,
            PermissionEnum::DispatchManage,
            PermissionEnum::VehicleViewAny,
            PermissionEnum::VehicleReserve,
            // Lagerwirtschaft (Feature 048): operative Bestandsbuchungen.
            PermissionEnum::InventoryViewAny,
            PermissionEnum::InventoryPost,
            // Vollaudit 2026-07 (M22): Freigabe-Rechte der Leitung.
            PermissionEnum::InventoryNegative,
            PermissionEnum::InventorySubstituteApprove,
            PermissionEnum::DutyPlanViewAny,
            PermissionEnum::DutyPlanCreate,
            PermissionEnum::DutyPlanUpdate,
            PermissionEnum::DutyPlanPublish,
            PermissionEnum::ShiftManage,
            PermissionEnum::ScheduledShiftManage,
            PermissionEnum::CoverageRequirementManage,
            PermissionEnum::OnCallShiftManage,
            PermissionEnum::EmergencyAssignmentManage,
            PermissionEnum::ShiftTypeManage,
            // Dienstplan-Intelligenz (Feature 007): Teamleitung gibt Tausch frei,
            // erhält Besetzungsvorschläge und beantragt/pflegt eigene Verfügbarkeit.
            PermissionEnum::ShiftExchangeRequest,
            PermissionEnum::ShiftExchangeApprove,
            PermissionEnum::AvailabilityManageOwn,
            PermissionEnum::StaffingSuggest,
            PermissionEnum::VacationViewAny,
            PermissionEnum::VacationApprove,
            PermissionEnum::VacationCancel,
            PermissionEnum::SickLeaveViewAny,
            PermissionEnum::SickLeaveManage,
            PermissionEnum::AttendanceViewAny,
            PermissionEnum::AttendanceManage,
            // ArbZG-Compliance auf Ist-Arbeitszeit (Feature 006).
            PermissionEnum::ComplianceViewAny,
            // Plan/Ist-Anwesenheit der eigenen Teams (Rang 38).
            PermissionEnum::ReportPresenceTeam,
            // Arbeitszeit-Modell pflegen jetzt exklusiv Personalverwaltung +
            // Geschäftsführung (work-schedule.manage daher hier entfernt).
            PermissionEnum::FlexBalanceView,
            PermissionEnum::FlexBalanceManage,
            PermissionEnum::TourViewAny,
            PermissionEnum::TourManage,
            PermissionEnum::TravelLogViewAny,
            PermissionEnum::OpenIssueViewAny,
            PermissionEnum::OpenIssueView,
            PermissionEnum::OpenIssueCreate,
            PermissionEnum::OpenIssueUpdate,
            PermissionEnum::OpenIssueAssign,
            PermissionEnum::OpenIssuePublishToCustomer,
            PermissionEnum::OpenIssueDelete,
            // Arbeitsschutz / Sicherheitsereignisse (Feature 013): Teamleitung
            // führt das Register (sehen, melden, bearbeiten/schließen).
            PermissionEnum::SafetyViewAny,
            PermissionEnum::SafetyReport,
            PermissionEnum::SafetyManage,
            // Benachrichtigungsregeln (MVP-018): Teamleitung lesend,
            // Bearbeitung bleibt Admin.
            PermissionEnum::NotificationRuleViewAny,
            PermissionEnum::CommunicationViewAny,
            PermissionEnum::CommunicationView,
            PermissionEnum::CommunicationCreate,
            PermissionEnum::CommunicationUpdate,
            PermissionEnum::CommunicationPublishToCustomer,
            // Dokumente (MVP-031): Teamleitung verwaltet inkl. Archivieren,
            // OHNE endgültiges Löschen (bleibt Admin).
            PermissionEnum::DocumentViewAny,
            PermissionEnum::DocumentView,
            PermissionEnum::DocumentCreate,
            PermissionEnum::DocumentUpdate,
            PermissionEnum::DocumentArchive,
            // Wissensbasis (Feature 011): Teamleitung redigiert und
            // veröffentlicht, OHNE endgültiges Löschen (bleibt Admin).
            PermissionEnum::KnowledgeViewAny,
            PermissionEnum::KnowledgeView,
            PermissionEnum::KnowledgeCreate,
            PermissionEnum::KnowledgeUpdate,
            PermissionEnum::KnowledgePublish,
            // Ideenlandkarten (Feature 054): eigene Karten anlegen; Inhalte
            // regeln Eigentum + Freigaben (IdeaMapPolicy), nicht das Recht.
            PermissionEnum::IdeasViewAny,
            PermissionEnum::IdeasCreate,
            // Formularsystem (Feature 032): Teamleitung pflegt Vorlagen
            // und sieht alle ausgefüllten Formulare.
            PermissionEnum::FormTemplateViewAny,
            PermissionEnum::FormTemplateManage,
            PermissionEnum::FormSubmissionViewAny,
            PermissionEnum::FormSubmissionView,
            PermissionEnum::FormSubmissionCreate,
            PermissionEnum::ServiceTicketView,
            PermissionEnum::ServiceTicketCreate,
            PermissionEnum::ServiceTicketUpdate,
            PermissionEnum::ServiceTicketAssign,
            PermissionEnum::ServiceTicketClose,
            PermissionEnum::HelpdeskQueueManage,
            PermissionEnum::HelpdeskTicketInternalNote,
            // Servicekatalog + Genehmigungs-Inbox (Feature 065, MVP-154).
            PermissionEnum::ServiceCatalogManage,
            PermissionEnum::ServiceRequestApprove,
            // Problem-Management (Feature 065, MVP-156).
            PermissionEnum::ServiceDeskProblemManage,
            // Change-/CAB-Management (Feature 065, MVP-157) — Freigaben
            // laufen über ServiceRequestApprove (eine Inbox-Mechanik).
            PermissionEnum::ServiceDeskChangeManage,
            PermissionEnum::SlaContractView,
            PermissionEnum::SlaContractManage,
            PermissionEnum::SlaViewAny,
            PermissionEnum::SlaManage,
            PermissionEnum::KeyHandoverView,
            PermissionEnum::KeyHandoverRecord,
            PermissionEnum::MeterReadingView,
            PermissionEnum::MeterReadingRecord,
            PermissionEnum::ProtocolViewAny,
            PermissionEnum::ProtocolView,
            PermissionEnum::ProtocolCreate,
            PermissionEnum::ProtocolEditDraft,
            PermissionEnum::ProtocolRequestReview,
            PermissionEnum::ProtocolSignInternal,
            PermissionEnum::ProtocolSignCustomer,
            PermissionEnum::ProtocolArchive,
            PermissionEnum::ProtocolSupersede,
            PermissionEnum::ProtocolDelete,
            PermissionEnum::ProtocolSignatureRequest,
            // Kunden-Rückfragen aus dem Portal (Feature 012): Teamleitung
            // sieht und beantwortet die Rückfragen der Kunden.
            PermissionEnum::ProtocolCustomerQueryManage,
            PermissionEnum::ProtocolPdfDownload,
            PermissionEnum::ProtocolItemPhotoAdd,
            PermissionEnum::ProtocolItemPhotoRemove,
            PermissionEnum::ProtocolItemPhotoViewGeo,
            // Externe Beteiligte (Feature 033): Teamleitung darf Subunternehmer/
            // Prüfer befristet zu Aufträgen/Protokollen einladen und widerrufen.
            PermissionEnum::ExternalParticipantManage,
            PermissionEnum::ProcedureTemplateView,
            PermissionEnum::ProcedureTemplateCreate,
            PermissionEnum::ProcedureTemplateUpdate,
            PermissionEnum::ProcedureTemplatePublish,
            PermissionEnum::ProcedureRunView,
            PermissionEnum::ProcedureRunStart,
            PermissionEnum::ProcedureRunExecute,
            PermissionEnum::ProcedureRunAbort,
            PermissionEnum::ProcedureBackupRegister,
            PermissionEnum::ProcedureBackupVerify,
            PermissionEnum::ProcedureBackupViewExternal,
            PermissionEnum::ProcedureSecondPersonRequest,
            PermissionEnum::ProcedureSecondPersonTake,
            PermissionEnum::ProcedureSecondPersonSign,
            PermissionEnum::ProcedureSecondPersonRevoke,
            PermissionEnum::ProcedureDeviationRecord,
            PermissionEnum::ProcedureDeviationAcceptRisk,
            PermissionEnum::ProcedureDeviationUpdate,
            PermissionEnum::ProcedureDeviationView,
            PermissionEnum::ClassificationList,
            PermissionEnum::ClassificationOrgView,
            PermissionEnum::ClassificationOrgManage,
            PermissionEnum::ClassificationOrgDeactivateDefault,
            PermissionEnum::ClassificationOrgImport,
            PermissionEnum::ClassificationRequirementView,
            PermissionEnum::ClassificationRequirementManage,
            PermissionEnum::AssetView,
            PermissionEnum::AssetCreate,
            PermissionEnum::AssetUpdate,
            PermissionEnum::AssetDecommission,
            PermissionEnum::AssetTransferOwnership,
            PermissionEnum::AssetCheckout,
            PermissionEnum::AssetDefectManage,
            PermissionEnum::ReportView,
            PermissionEnum::AccessAuditView,
        ];

        // Personalverwaltung (HR): pflegt Personal-/Lohndaten und das
        // Arbeitszeit-Modell, sieht Anwesenheit/Abwesenheit/Gleitzeit lesend.
        // KEINE Rollenvergabe, Passwörter, Neuanlage/Löschung (bleibt Admin).
        $personalverwaltung = [
            PermissionEnum::OrganizationView,
            PermissionEnum::UserViewAny,
            PermissionEnum::UserView,
            // Plan/Ist-Anwesenheit org-weit (Rang 38).
            PermissionEnum::ReportPresenceTeam,
            PermissionEnum::ReportPresenceOrganization,
            PermissionEnum::UserPayrollManage,
            PermissionEnum::UserFlexManage,
            PermissionEnum::WorkScheduleManage,
            PermissionEnum::AttendanceViewAny,
            // Zeitkorrekturen: Personalverwaltung trägt im Namen von Mitarbeitenden
            // nach und entscheidet darüber (vergessene Stempelungen etc.).
            PermissionEnum::CorrectionCreateOwn,
            PermissionEnum::CorrectionCreateForOthers,
            PermissionEnum::CorrectionSubmitOwn,
            PermissionEnum::CorrectionWithdrawOwn,
            PermissionEnum::CorrectionViewOrganization,
            PermissionEnum::CorrectionApprove,
            PermissionEnum::CorrectionReject,
            PermissionEnum::FlexBalanceView,
            PermissionEnum::VacationViewAny,
            PermissionEnum::SickLeaveViewAny,
            PermissionEnum::SickLeaveManage,
            PermissionEnum::ReportView,
            PermissionEnum::ClassificationList,
            // Feature 068: Personalbewerbungen sind HR-Hoheit — inkl.
            // Datenschutz-Aktionen (Aufbewahrung/Löschung/Auskunft/Talentpool).
            PermissionEnum::RecruitingViewAny,
            PermissionEnum::RecruitingView,
            PermissionEnum::RecruitingManage,
            PermissionEnum::RecruitingDecide,
            PermissionEnum::RecruitingPrivacy,
        ];

        $buchhaltung = [
            PermissionEnum::OrganizationView,
            // Feature 072: kaufmännische Reklamationsfolgen (Gutschrift/
            // Minderung/Storno) freigeben und übergeben.
            PermissionEnum::ClaimViewAny,
            PermissionEnum::ClaimView,
            PermissionEnum::ClaimFinance,
            // Feature 073: Mietpositionen/Kautionen freigeben und abrechnen.
            PermissionEnum::RentalViewAny,
            PermissionEnum::RentalView,
            PermissionEnum::RentalFinance,
            // Feature 100: Entsorgungsnachweise lesend (Abrechnung/Compliance).
            PermissionEnum::DisposalViewAny,
            PermissionEnum::DisposalView,
            // Feature 074: Leasingakten führen inkl. vertraulicher
            // Konditionen (Raten/Restwerte) und Fristen (Controlling).
            PermissionEnum::AssetFinanceViewAny,
            PermissionEnum::AssetFinanceView,
            PermissionEnum::AssetFinanceManage,
            PermissionEnum::AssetFinanceFinance,
            // Welle D (CLM): allgemeine Vertragsakten führen (Controlling).
            PermissionEnum::ContractViewAny,
            PermissionEnum::ContractView,
            PermissionEnum::ContractManage,
            // Feature 068: Wertpotenzial/Angebotsstände lesend (Forecast).
            PermissionEnum::TenderViewAny,
            PermissionEnum::TenderView,
            // Feature 069: Investitionsakten führen + freigeben (Controlling).
            PermissionEnum::InvestmentViewAny,
            PermissionEnum::InvestmentView,
            PermissionEnum::InvestmentManage,
            PermissionEnum::InvestmentApprove,
            PermissionEnum::DiaryViewAny,
            PermissionEnum::CommunicationViewAny,
            PermissionEnum::CommunicationView,
            // Dokumente (MVP-031): nur lesend (z. B. Verträge, Versicherungen).
            PermissionEnum::DocumentViewAny,
            PermissionEnum::DocumentView,
            PermissionEnum::CustomerViewAny,
            PermissionEnum::CustomerView,
            PermissionEnum::CustomerCreate,
            PermissionEnum::CustomerUpdate,
            PermissionEnum::CustomerDelete,
            PermissionEnum::CustomerExport,
            PermissionEnum::CustomerImport,
            PermissionEnum::CustomerLexofficeSync,
            PermissionEnum::ForeignCustomerViewAny,
            PermissionEnum::ForeignCustomerView,
            PermissionEnum::ForeignCustomerCreate,
            PermissionEnum::ForeignCustomerUpdate,
            PermissionEnum::ForeignCustomerDelete,
            PermissionEnum::ForeignCustomerPromote,
            PermissionEnum::SupplierViewAny,
            PermissionEnum::SupplierView,
            PermissionEnum::SupplierCreate,
            PermissionEnum::SupplierUpdate,
            PermissionEnum::SupplierDelete,
            PermissionEnum::SupplierExport,
            PermissionEnum::SupplierImport,
            PermissionEnum::SupplierLexofficeSync,
            PermissionEnum::ArticleViewAny,
            PermissionEnum::ArticleView,
            PermissionEnum::ArticleManage,
            PermissionEnum::ArticleLexofficeSync,
            // Produktmodell (MVP-369): gleiche Zielgruppe wie der Artikelstamm.
            PermissionEnum::ProductViewAny,
            PermissionEnum::ProductManage,
            // Cloud-Dokumenteingang (Feature 080, MVP-351): Org-Admin-Aufgabe
            // wie die übrigen Integrations-Anbindungen.
            PermissionEnum::CloudIntakeConnectionManage,
            PermissionEnum::CloudIntakeRouteManage,
            PermissionEnum::CloudIntakeRunPreview,
            PermissionEnum::VoucherViewAny,
            PermissionEnum::VoucherLexofficeSync,
            PermissionEnum::ProjectViewAny,
            PermissionEnum::ProjectView,
            PermissionEnum::ProjectManageBilling,
            PermissionEnum::TimeEntryViewAny,
            PermissionEnum::TimeEntryApprove,
            PermissionEnum::TimesheetViewAny,
            PermissionEnum::TimesheetSign,
            PermissionEnum::TimesheetLock,
            PermissionEnum::TimesheetUnlock,
            PermissionEnum::TimesheetExport,
            PermissionEnum::MonthViewOrganization,
            PermissionEnum::CorrectionViewOrganization,
            PermissionEnum::InvoiceViewAny,
            PermissionEnum::InvoiceView,
            PermissionEnum::InvoiceCreate,
            PermissionEnum::InvoiceUpdate,
            PermissionEnum::InvoiceDelete,
            PermissionEnum::InvoiceIssue,
            PermissionEnum::InvoicePay,
            PermissionEnum::InvoiceExport,
            PermissionEnum::OrderMarkInvoiced,
            // Finanzschnittstelle (Feature 045): Übergaben vorbereiten und
            // übertragen — bewusst OHNE finance.config (Konfiguration des
            // Fakturierungswegs bleibt dem Admin vorbehalten).
            PermissionEnum::FinanceViewAny,
            PermissionEnum::FinanceTransferTime,
            PermissionEnum::FinanceTransferMaterial,
            // Zahlungsabgleich (Feature 045, Priorität 3): Bankdatei importieren
            // und Zuordnungen bestätigen. Die Verwaltung eigener Bankkonten
            // (finance.config) bleibt dem Admin vorbehalten.
            PermissionEnum::FinancePaymentImport,
            PermissionEnum::FinancePaymentReconcile,
            // DATEV-Buchungsstapel (Feature 045, Priorität 2): buchungsreife
            // Belege exportieren. Die Buchhaltungs-Konfiguration (Konten,
            // Steuerschlüssel, Beraternummer) bleibt über finance.config dem
            // Admin vorbehalten.
            PermissionEnum::FinanceBookingExport,
            // GoBD-Z3-Datenträgerüberlassung (Feature 063): steuerrelevante
            // Daten für die Betriebsprüfung als GDPdU-Paket ausleiten.
            PermissionEnum::FinanceGobdExport,
            // Zuschlagsregeln (Feature 005): Lohnbüro pflegt die Regeln.
            PermissionEnum::SurchargeRuleViewAny,
            PermissionEnum::SurchargeRuleManage,
            // Kostenstellen-Regeln für den Zeitexport (Rang 35): gleiche Zielgruppe.
            PermissionEnum::CostCenterRuleViewAny,
            PermissionEnum::CostCenterRuleManage,
            // Lohnarten-Mapping + automatische Export-Lieferung (A21 · MVP-019):
            // gleiche Zielgruppe (Lohnbüro/Buchhaltung).
            PermissionEnum::WageTypeMappingViewAny,
            PermissionEnum::WageTypeMappingManage,
            PermissionEnum::ReportView,
            PermissionEnum::ReportExport,
            // ArbZG-Compliance auf Ist-Arbeitszeit (Feature 006).
            PermissionEnum::ComplianceViewAny,
            PermissionEnum::AuditLogView,
            PermissionEnum::UserViewAny,
            PermissionEnum::UserView,
            PermissionEnum::UserManageRates,
            PermissionEnum::UserFlexManage,
        ];

        $user = [
            PermissionEnum::OrganizationView,
            PermissionEnum::ProjectViewAny,
            PermissionEnum::ProjectView,
            PermissionEnum::TaskManage,
            PermissionEnum::MilestoneManage,
            PermissionEnum::TimeEntryViewOwn,
            PermissionEnum::TimeEntryCreate,
            PermissionEnum::TimeEntryUpdate,
            PermissionEnum::TimeEntryDelete,
            PermissionEnum::TimesheetCreate,
            PermissionEnum::TimesheetUpdate,
            PermissionEnum::TimesheetSign,
            PermissionEnum::MonthViewOwn,
            PermissionEnum::MonthSubmitOwn,
            PermissionEnum::CorrectionCreateOwn,
            PermissionEnum::CorrectionSubmitOwn,
            PermissionEnum::CorrectionWithdrawOwn,
            // Tagesabschluss (MVP-015): eigene Tage sehen, abschließen,
            // Korrektur anfordern (§7).
            PermissionEnum::DayCloseViewOwn,
            PermissionEnum::DayCloseCloseOwn,
            PermissionEnum::DayCloseRequestCorrectionOwn,
            PermissionEnum::DiaryViewOwn,
            PermissionEnum::DiaryCreate,
            PermissionEnum::DiaryUpdate,
            PermissionEnum::DiaryDelete,
            PermissionEnum::OrderAccept,
            PermissionEnum::OrderWork,
            PermissionEnum::OrderComplete,
            PermissionEnum::OrderHandover,
            PermissionEnum::OrderCancel,
            PermissionEnum::VacationRequest,
            PermissionEnum::AttendanceManage,
            // Dienstplan-Intelligenz (Feature 007): Mitarbeitende beantragen
            // Schichttausch und pflegen ihre eigene Verfügbarkeit/Wunschdienste.
            PermissionEnum::ShiftExchangeRequest,
            PermissionEnum::AvailabilityManageOwn,
            PermissionEnum::FlexBalanceView,
            PermissionEnum::TourViewAny,
            PermissionEnum::TravelLogViewAny,
            PermissionEnum::TravelLogManage,
            PermissionEnum::OpenIssueViewAny,
            PermissionEnum::OpenIssueView,
            PermissionEnum::OpenIssueCreate,
            PermissionEnum::OpenIssueUpdate,
            PermissionEnum::CommunicationViewAny,
            PermissionEnum::CommunicationView,
            PermissionEnum::CommunicationCreate,
            PermissionEnum::CommunicationUpdate,
            // Dokumente (MVP-031): sehen, hochladen, eigene bearbeiten
            // (DocumentPolicy beschränkt update auf den Erfasser).
            PermissionEnum::DocumentViewAny,
            PermissionEnum::DocumentView,
            PermissionEnum::DocumentCreate,
            PermissionEnum::DocumentUpdate,
            // Wissensbasis (Feature 011): lesen, erfassen, EIGENE Entwürfe
            // pflegen (KnowledgeArticlePolicy beschränkt update entsprechend).
            PermissionEnum::KnowledgeViewAny,
            PermissionEnum::KnowledgeView,
            PermissionEnum::KnowledgeCreate,
            PermissionEnum::KnowledgeUpdate,
            // Ideenlandkarten (Feature 054): eigene Karten anlegen; Inhalte
            // regeln Eigentum + Freigaben (IdeaMapPolicy), nicht das Recht.
            PermissionEnum::IdeasViewAny,
            PermissionEnum::IdeasCreate,
            // Formularsystem (Feature 032): ausfüllen + EIGENE Submissions
            // einsehen (FormSubmissionPolicy beschränkt view entsprechend).
            PermissionEnum::FormSubmissionViewAny,
            PermissionEnum::FormSubmissionView,
            PermissionEnum::FormSubmissionCreate,
            PermissionEnum::ProtocolView,
            PermissionEnum::ProtocolCreate,
            PermissionEnum::ProtocolEditDraft,
            PermissionEnum::ProtocolItemPhotoAdd,
            PermissionEnum::ProtocolItemPhotoRemove,
            PermissionEnum::ProcedureTemplateView,
            PermissionEnum::ProcedureRunView,
            PermissionEnum::ProcedureRunStart,
            PermissionEnum::ProcedureRunExecute,
            PermissionEnum::ProcedureBackupRegister,
            PermissionEnum::ProcedureSecondPersonRequest,
            PermissionEnum::ProcedureSecondPersonSign,
            PermissionEnum::ProcedureDeviationRecord,
            PermissionEnum::ProcedureDeviationView,
            PermissionEnum::ClassificationList,
            PermissionEnum::ClassificationOrgView,
            PermissionEnum::ClassificationRequirementView,
            PermissionEnum::AssetView,
            PermissionEnum::AssetCreate,
            PermissionEnum::AssetUpdate,
            // Ausgabe/Rückgabe + Defektverwaltung (Feature 009): Teamleitung
            // führt den Bestand operativ.
            PermissionEnum::AssetCheckout,
            PermissionEnum::AssetDefectManage,
        ];

        // Außendienst: schlanker als user, dafür mit vollem Touren-/Spesen-
        // Funktionsumfang und KEINER Mitarbeiter-/Finanz-/Planungssicht.
        $aussendienst = [
            PermissionEnum::OrganizationView,
            PermissionEnum::CustomerViewAny,
            PermissionEnum::CustomerView,
            PermissionEnum::ProjectViewAny,
            PermissionEnum::ProjectView,
            PermissionEnum::TaskManage,
            PermissionEnum::TimeEntryViewOwn,
            PermissionEnum::TimeEntryCreate,
            PermissionEnum::TimeEntryUpdate,
            PermissionEnum::TimeEntryDelete,
            PermissionEnum::TimesheetCreate,
            PermissionEnum::TimesheetUpdate,
            PermissionEnum::TimesheetSign,
            PermissionEnum::MonthViewOwn,
            PermissionEnum::MonthSubmitOwn,
            PermissionEnum::CorrectionCreateOwn,
            PermissionEnum::CorrectionSubmitOwn,
            PermissionEnum::CorrectionWithdrawOwn,
            // Tagesabschluss (MVP-015): eigene Tage (§7) — wie Rolle user.
            PermissionEnum::DayCloseViewOwn,
            PermissionEnum::DayCloseCloseOwn,
            PermissionEnum::DayCloseRequestCorrectionOwn,
            PermissionEnum::DiaryViewOwn,
            PermissionEnum::DiaryCreate,
            PermissionEnum::DiaryUpdate,
            PermissionEnum::DiaryDelete,
            PermissionEnum::OrderAccept,
            PermissionEnum::OrderWork,
            PermissionEnum::OrderComplete,
            PermissionEnum::OrderHandover,
            PermissionEnum::OrderCancel,
            PermissionEnum::TourViewAny,
            PermissionEnum::TravelLogViewAny,
            PermissionEnum::TravelLogManage,
            PermissionEnum::VehicleViewAny,
            PermissionEnum::EnergyLogManage,
            PermissionEnum::VacationRequest,
            PermissionEnum::AttendanceManage,
            // Dienstplan-Intelligenz (Feature 007): auch der Außendienst kann
            // Schichten tauschen und eigene Verfügbarkeit pflegen.
            PermissionEnum::ShiftExchangeRequest,
            PermissionEnum::AvailabilityManageOwn,
            PermissionEnum::FlexBalanceView,
            PermissionEnum::OpenIssueViewAny,
            PermissionEnum::OpenIssueView,
            PermissionEnum::OpenIssueCreate,
            PermissionEnum::OpenIssueUpdate,
            // Arbeitsschutz (Feature 013): Außendienst meldet Ereignisse vom
            // Einsatz; Registerführung/Abschluss bleibt Teamleitung/Admin.
            PermissionEnum::SafetyReport,
            PermissionEnum::CommunicationViewAny,
            PermissionEnum::CommunicationView,
            PermissionEnum::CommunicationCreate,
            PermissionEnum::CommunicationUpdate,
            // Wissensbasis (Feature 011): lesen + erfassen (Lösungen aus
            // dem Einsatz festhalten), Redaktion bleibt Innendienst.
            PermissionEnum::KnowledgeViewAny,
            PermissionEnum::KnowledgeView,
            PermissionEnum::KnowledgeCreate,
            // Ideenlandkarten (Feature 054): eigene Karten auch mobil.
            PermissionEnum::IdeasViewAny,
            PermissionEnum::IdeasCreate,
            // Formularsystem (Feature 032): Außendienst füllt Formulare im
            // Einsatz aus; Vorlagenpflege bleibt Teamleitung/Admin.
            PermissionEnum::FormSubmissionViewAny,
            PermissionEnum::FormSubmissionView,
            PermissionEnum::FormSubmissionCreate,
            PermissionEnum::ProtocolView,
            PermissionEnum::ProtocolCreate,
            PermissionEnum::ProtocolEditDraft,
            PermissionEnum::ProtocolRequestReview,
            PermissionEnum::ProtocolSignInternal,
            PermissionEnum::ProtocolPdfDownload,
            PermissionEnum::ProtocolItemPhotoAdd,
            PermissionEnum::ProtocolItemPhotoRemove,
            PermissionEnum::ClassificationList,
            PermissionEnum::ClassificationOrgView,
            PermissionEnum::ClassificationRequirementView,
            PermissionEnum::AssetView,
            PermissionEnum::AssetCreate,
            PermissionEnum::AssetUpdate,
            // Außendienst nimmt Geräte mit auf den Einsatz (Feature 009):
            // Ausgabe/Rückgabe ja, Defektverwaltung bleibt Teamleitung/Admin.
            PermissionEnum::AssetCheckout,
        ];

        $callcenter = [
            PermissionEnum::OrganizationView,
            PermissionEnum::DiaryViewAny,
            PermissionEnum::DiaryCreate,
            PermissionEnum::DiaryCreateForOthers,
            PermissionEnum::DiaryUpdate,
            PermissionEnum::OrderAccept,
            PermissionEnum::OrderCancel,
            PermissionEnum::CustomerViewAny,
            PermissionEnum::CustomerView,
            PermissionEnum::CommunicationViewAny,
            PermissionEnum::CommunicationView,
            PermissionEnum::CommunicationCreate,
            PermissionEnum::CommunicationUpdate,
            PermissionEnum::ClassificationList,
            PermissionEnum::ClassificationOrgView,
            PermissionEnum::ClassificationRequirementView,
            PermissionEnum::AssetView,
        ];

        // Support (Anbieter-Support): strikt read-only über fast alle Bereiche
        // plus Auditzugriff. KEINE Create/Update/Delete-Permissions.
        $support = [
            PermissionEnum::OrganizationView,
            // Feature 072: Reklamationsannahme aus Helpdesk/Telefon.
            PermissionEnum::ClaimViewAny,
            PermissionEnum::ClaimView,
            PermissionEnum::ClaimManage,
            PermissionEnum::UserViewAny,
            PermissionEnum::UserView,
            PermissionEnum::CustomerViewAny,
            PermissionEnum::CustomerView,
            PermissionEnum::ProjectViewAny,
            PermissionEnum::ProjectView,
            PermissionEnum::TimeEntryViewAny,
            PermissionEnum::TimesheetViewAny,
            PermissionEnum::InvoiceViewAny,
            PermissionEnum::InvoiceView,
            PermissionEnum::DiaryViewAny,
            PermissionEnum::DutyPlanViewAny,
            PermissionEnum::VacationViewAny,
            PermissionEnum::SickLeaveViewAny,
            PermissionEnum::AttendanceViewAny,
            PermissionEnum::TourViewAny,
            PermissionEnum::TravelLogViewAny,
            PermissionEnum::VehicleViewAny,
            PermissionEnum::ReportView,
            PermissionEnum::AuditLogView,
            PermissionEnum::AccessAuditView,
            PermissionEnum::FlexBalanceView,
            PermissionEnum::ClassificationList,
            PermissionEnum::ClassificationOrgView,
            PermissionEnum::ClassificationRequirementView,
            PermissionEnum::AssetView,
            // Datenschutzseite (MVP-005): Support sieht die Seite read-only,
            // ohne Widerruf-Knöpfe. Die *.view-Permissions liegen für die
            // Geschäftsführung bereits implizit über die `.view`-Heuristik;
            // Support hat keine Heuristik, daher explizit.
            PermissionEnum::PrivacyView,
            PermissionEnum::PrivacySessionsView,
            PermissionEnum::PrivacyTokensView,
            PermissionEnum::PrivacyIntegrationsView,
            PermissionEnum::PrivacyExportsView,
            PermissionEnum::PrivacySupportView,
        ];

        // Rolle `kunde`: read-only Zugriff auf das Customer-Portal, ausschliesslich
        // auf die EIGENEN Datensaetze des verknuepften Kunden. Wird vom
        // `customer`-Guard ausgewertet; interne Routen sind durch den separaten
        // Provider technisch nicht erreichbar.
        $kunde = [
            PermissionEnum::CustomerPortalAccess,
            PermissionEnum::CustomerPortalDiaryView,
            PermissionEnum::CustomerPortalTimeEntryView,
            PermissionEnum::CustomerPortalInvoiceView,
            PermissionEnum::CustomerPortalOpenIssueView,
        ];

        // Agiles Projektmanagement (Feature 064): Teamleitung führt Boards/
        // Sprints, User arbeiten auf dem Board (View + Move); GF erhält
        // agile.view/agile.report.view über die .view-Heuristik.
        $agileWork = [
            PermissionEnum::AgileView,
            PermissionEnum::AgileWorkItemMove,
        ];
        $agileLead = [
            ...$agileWork,
            PermissionEnum::AgileBoardManage,
            PermissionEnum::AgileBacklogPrioritize,
            PermissionEnum::AgileSprintManage,
            PermissionEnum::AgileWorkflowOverride,
            PermissionEnum::AgileReportView,
        ];

        // Lese-Rollen, die Kunden sehen, sehen auch deren Fremdkunden.
        $foreignCustomerRead = [
            PermissionEnum::ForeignCustomerViewAny,
            PermissionEnum::ForeignCustomerView,
        ];

        // Arbeits-Teams: interne Rollen dürfen Teams sehen; Verwaltung
        // (Anlegen/Bearbeiten/Löschen/Mitglieder) nur Teamleitung + Admin.
        // Geschäftsführung erhält .viewAny/.view bereits über die Heuristik oben.
        $teamRead = [
            PermissionEnum::TeamViewAny,
            PermissionEnum::TeamView,
        ];
        $teamManage = [
            ...$teamRead,
            PermissionEnum::TeamCreate,
            PermissionEnum::TeamUpdate,
            PermissionEnum::TeamDelete,
            PermissionEnum::TeamManageMembers,
        ];

        return [
            UserRole::Admin->value => $all,
            UserRole::Geschaeftsfuehrung->value => $geschaeftsfuehrung,
            UserRole::Personalverwaltung->value => [...$personalverwaltung, ...$teamRead],
            UserRole::Teamleitung->value => [...$teamleitung, ...$foreignCustomerRead, ...$teamManage, ...$agileLead],
            UserRole::Buchhaltung->value => [...$buchhaltung, ...$teamRead],
            UserRole::User->value => [...$user, ...$foreignCustomerRead, ...$teamRead, ...$agileWork],
            UserRole::Aussendienst->value => [...$aussendienst, ...$foreignCustomerRead, ...$teamRead],
            UserRole::Callcenter->value => [...$callcenter, ...$foreignCustomerRead, ...$teamRead],
            UserRole::Support->value => [...$support, ...$foreignCustomerRead, ...$teamRead],
            UserRole::Kunde->value => $kunde,
        ];
    }
}
