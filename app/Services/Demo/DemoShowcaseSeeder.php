<?php
/*
 * Created on   : Mon Jul 13 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DemoShowcaseSeeder.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Services\Demo;

use App\Models\{Customer, Invoice, Organization, Project, User};
use Illuminate\Support\Collection;

/**
 * Feature-Vorführszenarien des Demo-Mandanten (Agile Boards, Helpdesk,
 * §-19-Faktura, Bewerbungen, Investitionen, Krise, Nachhaltigkeit,
 * Reklamation, Verleih, Leasing, Prüfmittel). Aus dem DemoSeederService
 * extrahiert (Refactoring Welle 2, B6b); wird ausschließlich innerhalb
 * dessen Seed-Transaktion aufgerufen. Alle Szenarien sind robust:
 * Fehler (z. B. deaktivierte Module) brechen den Gesamt-Seed nicht ab.
 */
class DemoShowcaseSeeder {
    /**
     * §-19-Demo-Ablauf (Feature 066): dokumentiert die Belegkette einer
     * Kleinunternehmer-Org — Angebot mit Annahme, Überführung in eine
     * Entwurfsrechnung (TaxResolver → 0 %, §-19-Hinweistext) und
     * Ausstellung. Bewusst OHNE die Org global auf §19 zu stellen: der
     * Steuerkontext wird am Demo-Kunden über den Org-Setting-Schalter nur
     * für die Belegerzeugung aktiviert und danach zurückgesetzt.
     */
    public function seedSmallBusinessInvoicing(Organization $organization, Customer $customer, ?User $actor): int {
        if ($actor === null) {
            return 0;
        }

        // §-19-Kontext temporär aktivieren (data_get-Konvention, s. TaxResolver).
        $settings = (array) ($organization->settings ?? []);
        $before = $settings['einvoice']['small_business'] ?? null;
        $settings['einvoice']['small_business'] = '1';
        $organization->settings = $settings;
        $organization->save();

        try {
            $quotes = app(\App\Services\Invoicing\QuoteService::class);
            $quote = $quotes->create([
                'customer_id' => $customer->id,
                'valid_until' => \Carbon\Carbon::now()->addDays(30)->toDateString(),
                'terms' => (string) __('Demo-Angebot: Wartung inkl. Anfahrt, Abrechnung nach Aufwand.'),
            ], [
                ['description' => (string) __('Wartungspauschale (Demo)'), 'quantity' => '1', 'unit' => 'Pauschale', 'unit_price' => '480.00'],
                ['description' => (string) __('Erweiterte Dokumentation (Option)'), 'quantity' => '1', 'unit' => 'Pauschale', 'unit_price' => '120.00', 'optional' => true],
            ], $actor);
            $quote = $quotes->approve($quote, $actor);
            ['quote' => $quote] = $quotes->send($quote, $actor);
            $quote = $quotes->accept($quote); // Vollannahme (Optionen bleiben draußen)
            $invoice = $quotes->convertToInvoice($quote, $actor);

            // Ausstellen: friert Parteien ein; §-19-Hinweis steht in den Notes.
            $invoice->freezeParties();
            $invoice->update([
                'status' => Invoice::STATUS_ISSUED,
                'issued_on' => \Carbon\Carbon::now(),
                'due_on' => \Carbon\Carbon::now()->addDays((int) ($invoice->payment_terms_days ?? 14)),
            ]);

            return 1;
        } catch (\Throwable $e) {
            // Demo-Seeder bleibt robust: fehlende Vertriebs-Voraussetzungen
            // (z. B. deaktiviertes Modul) brechen den Gesamt-Seed nicht ab.
            \Illuminate\Support\Facades\Log::info('Demo-Seeder: §19-Fakturakette übersprungen: ' . $e->getMessage());

            return 0;
        } finally {
            $settings = (array) ($organization->settings ?? []);
            if ($before === null) {
                unset($settings['einvoice']['small_business']);
            } else {
                $settings['einvoice']['small_business'] = $before;
            }
            $organization->settings = $settings;
            $organization->save();
        }
    }

    /**
     * Demo Feature 068: Ausschreibung (Go → Anforderung erledigt →
     * Einreichung → gewonnen) + Personalbewerbung (Gespräch → Bewertung →
     * Zusage → Mitarbeiter-Entwurf). Robust: Fehler brechen den Seed nicht.
     */
    public function seedApplications(Organization $organization, Customer $customer, ?User $actor): int {
        if ($actor === null) {
            return 0;
        }

        try {
            $tenders = app(\App\Services\Applications\TenderService::class);
            $opportunity = \App\Models\Applications\ApplicationOpportunity::query()->create([
                'organization_id' => $organization->id,
                'title' => (string) __('Rahmenvertrag Wartung Bürokomplex (Demo)'),
                'kind' => 'framework',
                'source' => 'Vergabeportal (Demo)',
                'customer_id' => $customer->id,
                'status' => 'in_progress',
                'submission_deadline' => \Carbon\Carbon::now()->addDays(14)->toDateString(),
                'estimated_value' => '48000.00',
                'probability' => 60,
                'responsible_user_id' => $actor->id,
                'created_by' => $actor->id,
            ]);
            $opportunity->requirements()->create([
                'organization_id' => $organization->id,
                'label' => (string) __('Referenzliste vergleichbarer Objekte'),
                'kind' => 'proof',
                'required' => true,
                'status' => 'done',
                'position' => 1,
            ]);
            $tenders->decideGo($opportunity, 'go', (string) __('Passt zur Auslastung im Winterhalbjahr.'), $actor);
            $tenders->submit($opportunity->refresh(), 'portal', null, $actor);
            $tenders->decide($opportunity->refresh(), 'won', null, $actor);

            $recruiting = app(\App\Services\Applications\RecruitingService::class);
            $requisition = \App\Models\Applications\JobRequisition::query()->create([
                'organization_id' => $organization->id,
                'title' => (string) __('Servicetechniker:in (Demo)'),
                'employment_type' => 'full_time',
                'status' => 'open',
                'responsible_user_id' => $actor->id,
                'created_by' => $actor->id,
            ]);
            ['application' => $application] = $recruiting->intake([
                'job_requisition_id' => $requisition->id,
                'candidate_name' => 'Kim Beispiel',
                'email' => 'kim.beispiel@example.test',
                'source' => 'website',
            ], $actor);
            $application->interviews()->create([
                'organization_id' => $organization->id,
                'scheduled_at' => \Carbon\Carbon::now()->subDays(3),
                'mode' => 'onsite',
                'interviewer_id' => $actor->id,
                'status' => 'done',
                'rating' => 5,
            ]);
            $recruiting->decide($application->refresh(), 'accepted', null, $actor);
            $recruiting->createEmployeeDraft($application->refresh(), $actor, [(string) __('Elektrofachkraft')]);

            return 2; // 1 Ausschreibung + 1 Bewerbungskette
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::info('Demo-Seeder: Bewerbungs-Demo übersprungen: ' . $e->getMessage());

            return 0;
        }
    }

    /**
     * Demo Feature 069: Investitionsakte mit Variantenvergleich und
     * eingereichtem Budgetantrag (Freigabe bewusst offen — Vorführung
     * der Kette). Robust: Fehler brechen den Seed nicht.
     */
    public function seedInvestments(Organization $organization, ?User $actor): int {
        if ($actor === null) {
            return 0;
        }

        try {
            $case = \App\Models\Investments\InvestmentCase::query()->create([
                'organization_id' => $organization->id,
                'title' => (string) __('Ersatz Servicefahrzeug (Demo)'),
                'category' => 'machine',
                'reason' => (string) __('Bestandsfahrzeug hat 280.000 km und steigende Reparaturkosten.'),
                'objective' => (string) __('Ausfallsicherheit im Außendienst, geringere Werkstattkosten.'),
                'urgency' => 'high',
                'status' => 'comparison',
                'responsible_user_id' => $actor->id,
                'created_by' => $actor->id,
            ]);
            $case->options()->create([
                'organization_id' => $organization->id,
                'title' => (string) __('Neufahrzeug Kauf (Demo)'),
                'one_time_cost' => '42000.00',
                'recurring_cost_yearly' => '1800.00',
                'delivery_weeks' => 16,
                'quality_score' => 5,
                'recommended' => true,
            ]);
            $case->options()->create([
                'organization_id' => $organization->id,
                'title' => (string) __('Jahreswagen (Demo)'),
                'one_time_cost' => '31000.00',
                'recurring_cost_yearly' => '2400.00',
                'delivery_weeks' => 3,
                'quality_score' => 4,
            ]);
            app(\App\Services\Investments\InvestmentService::class)->submitBudget($case->refresh(), [
                'amount' => '42000.00',
                'cost_kind' => 'purchase',
                'financing' => 'loan',
            ], $actor);

            return 1;
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::info('Demo-Seeder: Investitions-Demo übersprungen: ' . $e->getMessage());

            return 0;
        }
    }

    /** Demo Feature 070: geplante Krisenübung (Playbook-Verbesserung). */
    public function seedCrisisExercise(Organization $organization, ?User $actor): int {
        if ($actor === null) {
            return 0;
        }

        try {
            \App\Models\Crisis\CrisisExercise::query()->create([
                'organization_id' => $organization->id,
                'title' => (string) __('Stabsübung IT-Ausfall (Demo)'),
                'scenario' => (string) __('Zentraler Server fällt aus; Wiederanlauf nach Playbook, Kommunikation an Kunden binnen 4 Stunden.'),
                'next_due_on' => \Carbon\Carbon::now()->addDays(21)->toDateString(),
                'created_by' => $actor->id,
            ]);

            return 1;
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::info('Demo-Seeder: Krisenübung übersprungen: ' . $e->getMessage());

            return 0;
        }
    }

    /**
     * Reklamations-Demo (Feature 072, MVP-256): ein bewerteter und
     * entschiedener Fall inkl. Nachweis — ohne Lager-/Faktura-Folgen,
     * damit der Demo-Bestand konsistent bleibt.
     */
    public function seedClaims(Organization $organization, ?User $actor): int {
        if ($actor === null) {
            return 0;
        }

        try {
            $customer = \App\Models\Customer::query()
                ->where('organization_id', $organization->id)
                ->orderBy('id')
                ->first();
            if ($customer === null) {
                return 0;
            }

            $service = app(\App\Services\Claims\ClaimCaseService::class);
            $case = $service->open($organization, $actor, [
                'title' => (string) __('Thermostatventil tropft nach Wartung (Demo)'),
                'source' => 'phone',
                'priority' => 'high',
                'severity' => 'minor',
                'customer_id' => $customer->id,
                'description' => (string) __('Kunde meldet Tropfbildung am neu eingebauten Ventil im Bad.'),
                'responsible_user_id' => $actor->id,
            ]);
            $case->evidence()->create([
                'organization_id' => $organization->id,
                'kind' => 'photo',
                'title' => (string) __('Foto der Tropfstelle (Demo)'),
                'recorded_by' => $actor->id,
                'recorded_at' => now(),
            ]);
            $service->assess($case, $actor, \App\Enums\Claims\ClaimKind::WarrantyLegal, \App\Enums\Claims\ClaimVerdict::Justified, (string) __('Einbau vor 3 Monaten — gesetzliche Gewährleistung greift, Nacherfüllung angeboten.'));
            $service->decide($case->refresh(), $actor, 'accepted', (string) __('Nacherfüllung durch erneuten Serviceeinsatz (§ 439 BGB).'));
            $case->refresh()->actions()->create([
                'organization_id' => $organization->id,
                'kind' => 'service_visit',
                'status' => 'planned',
                'title' => (string) __('Nachbesserung vor Ort einplanen (Demo)'),
                'assigned_user_id' => $actor->id,
                'created_by' => $actor->id,
            ]);

            return 1;
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::info('Demo-Seeder: Reklamations-Demo übersprungen: ' . $e->getMessage());

            return 0;
        }
    }

    public function seedRental(Organization $organization, ?User $actor): int {
        if ($actor === null) {
            return 0;
        }

        try {
            $customer = \App\Models\Customer::query()
                ->where('organization_id', $organization->id)
                ->orderBy('id')
                ->first();
            $asset = \App\Models\Asset::query()
                ->where('organization_id', $organization->id)
                ->orderBy('id')
                ->first();
            if ($customer === null || $asset === null) {
                return 0;
            }

            // Versionierte Preisliste (D10) mit Tagessatz + Reinigung.
            $card = \App\Models\Rental\RentalRateCard::query()->create([
                'organization_id' => $organization->id,
                'name' => (string) __('Standard-Verleih (Demo)'),
                'version' => 1,
                'status' => \App\Enums\Rental\RentalRateCardStatus::Active->value,
                'valid_from' => now()->toDateString(),
                'created_by' => $actor->id,
            ]);
            $card->items()->createMany([
                ['organization_id' => $organization->id, 'kind' => 'daily_rate', 'label' => (string) __('Tagessatz (Demo)'), 'amount' => '45.00', 'unit' => 'day'],
                ['organization_id' => $organization->id, 'kind' => 'cleaning', 'label' => (string) __('Endreinigung (Demo)'), 'amount' => '25.00', 'unit' => 'flat'],
            ]);

            \App\Models\Rental\RentalProfile::query()->create([
                'organization_id' => $organization->id,
                'asset_id' => $asset->id,
                'is_rentable' => true,
                'group_code' => 'demo',
                'buffer_after_hours' => 2,
                'default_rate_card_id' => $card->id,
            ]);

            $service = app(\App\Services\Rental\RentalCaseService::class);
            $case = $service->open($organization, $actor, [
                'customer_id' => $customer->id,
                'starts_at' => now()->addDay()->setTime(8, 0),
                'ends_at' => now()->addDays(3)->setTime(17, 0),
                'responsible_user_id' => $actor->id,
                'rental_rate_card_id' => $card->id,
                'deposit_amount' => '150.00',
                'notes' => (string) __('Demo-Verleihvorgang mit Konditionen-Snapshot.'),
            ], [$asset->id]);
            $service->reserve($case, $actor);

            return 1;
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::info('Demo-Seeder: Verleih-Demo übersprungen: ' . $e->getMessage());

            return 0;
        }
    }

    public function seedDisposal(Organization $organization, ?User $actor): int {
        if ($actor === null) {
            return 0;
        }

        try {
            $customer = \App\Models\Customer::query()
                ->where('organization_id', $organization->id)
                ->orderBy('id')
                ->first();
            if ($customer === null) {
                return 0;
            }

            // Entsorgungsfachbetrieb als externes Kontaktprofil (Feature 033).
            $disposer = \App\Models\ExternalContact::query()->create([
                'organization_id' => $organization->id,
                'name' => (string) __('Muster-Entsorgung GmbH (Demo)'),
                'email' => 'entsorgung@example.com',
                'role' => (string) __('Entsorgungsfachbetrieb'),
                'party' => 'other',
            ]);

            $service = app(\App\Services\Disposal\DisposalJobService::class);
            $job = $service->open($organization, $actor, [
                'customer_id' => $customer->id,
                'responsible_user_id' => $actor->id,
                'picked_up_on' => now()->subDays(3)->toDateString(),
                'notes' => (string) __('Demo-Entsorgungsvorgang: Altgeräte-Abholung mit Datenträger-Behandlung.'),
            ]);

            $server = $service->addItem($job, $actor, [
                'category' => (string) __('Server'),
                'manufacturer' => 'Muster-IT',
                'serial_number' => 'DEMO-SRV-001',
                'quantity' => 1,
                'weight_kg' => '18.5',
                'avv_code' => '20 01 35*',
                'has_data_storage' => true,
            ]);
            $service->addItem($job, $actor, [
                'category' => (string) __('Monitor'),
                'quantity' => 4,
                'weight_kg' => '22.0',
                'avv_code' => '20 01 36',
            ]);

            $service->transition($job->refresh(), $actor, \App\Enums\Disposal\DisposalJobStatus::Collected);
            $service->transition($job->refresh(), $actor, \App\Enums\Disposal\DisposalJobStatus::InTreatment);

            $service->addTreatment($server, $actor, [
                'media_type' => \App\Enums\Disposal\DataMediumType::Hdd->value,
                'method' => \App\Enums\Disposal\MediaTreatmentMethod::Shredding->value,
                'din_category' => \App\Enums\Disposal\DinCategory::H->value,
                'security_level' => 5,
                'protection_class' => 2,
                'treated_at' => now()->subDays(2),
                'evidence_reference' => 'DEMO-VERNICHTUNG-4711',
            ]);

            $service->transition($job->refresh(), $actor, \App\Enums\Disposal\DisposalJobStatus::HandedOver);
            $service->addHandover($job->refresh(), $actor, [
                'external_contact_id' => $disposer->id,
                'proof_type' => \App\Enums\Disposal\DisposalProofType::TransferNote->value,
                'document_number' => 'UES-2026-0815',
                'handed_over_on' => now()->subDay()->toDateString(),
                'certificate_reference' => 'EfbV-Zert. DEMO-99',
            ]);
            // Bewusst nicht abgeschlossen: das Prüfpanel zeigt die fehlende
            // Übernahme-Unterschrift als letztes Abschluss-Gate.

            return 1;
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::info('Demo-Seeder: Entsorgungs-Demo übersprungen: ' . $e->getMessage());

            return 0;
        }
    }

    public function seedAssetFinance(Organization $organization, ?User $actor): int {
        if ($actor === null) {
            return 0;
        }

        try {
            $asset = \App\Models\Asset::query()
                ->where('organization_id', $organization->id)
                ->orderBy('id')
                ->first();
            if ($asset === null) {
                return 0;
            }

            $service = app(\App\Services\AssetFinance\AssetFinanceService::class);
            $contract = $service->create($organization, $actor, [
                'kind' => \App\Enums\AssetFinance\AssetFinanceKind::OperatingLease->value,
                'partner_name' => (string) __('Muster-Leasing GmbH (Demo)'),
                'contract_no' => 'ML-2026-0042',
                'starts_on' => now()->startOfMonth()->toDateString(),
                'ends_on' => now()->startOfMonth()->addMonths(11)->toDateString(),
                'payment_rhythm' => 'monthly',
                'rate_amount' => '390.00',
                'residual_value' => '4500.00',
                'responsible_user_id' => $actor->id,
                'notes' => (string) __('Demo-Leasingakte mit Ratenplan und Kündigungsfrist.'),
            ], [$asset->id]);
            $service->activate($contract, $actor);

            $contract->deadlines()->create([
                'organization_id' => $organization->id,
                'kind' => \App\Enums\AssetFinance\AssetFinanceDeadlineKind::Termination->value,
                'due_on' => now()->addMonths(8)->toDateString(),
                'warn_days_before' => 60,
                'responsible_user_id' => $actor->id,
                'note' => (string) __('Kündigung spätestens 3 Monate vor Vertragsende (Demo).'),
            ]);
            $contract->usageLimits()->create([
                'organization_id' => $organization->id,
                'kind' => \App\Enums\AssetFinance\AssetFinanceUsageLimitKind::OperatingHours->value,
                'limit_value' => '1200.00',
                'period' => 'yearly',
                'overrun_fee_per_unit' => '2.5000',
            ]);

            return 1;
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::info('Demo-Seeder: Leasing-Demo übersprungen: ' . $e->getMessage());

            return 0;
        }
    }

    public function seedAssetCompliance(Organization $organization, ?User $actor): int {
        if ($actor === null) {
            return 0;
        }

        try {
            $asset = \App\Models\Asset::query()
                ->where('organization_id', $organization->id)
                ->orderBy('id')
                ->first();
            $profile = \App\Models\AssetCompliance\AssetComplianceProfile::query()
                ->whereNull('organization_id')
                ->where('code', 'dguv_v3_portable')
                ->first();
            if ($asset === null || $profile === null) {
                return 0;
            }

            $service = app(\App\Services\AssetCompliance\AssetComplianceService::class);
            $assignment = $service->assign($profile, $asset, $actor, [
                'last_done_on' => now()->subMonths(11)->toDateString(),
                'responsible_user_id' => $actor->id,
            ]);

            $service->recordInspection($assignment, $actor, [
                'result' => 'passed',
                'note' => (string) __('Demo-Prüfung ohne Befund.'),
                'signature_name' => $actor->name,
                'certificate' => [
                    'certificate_no' => 'KAL-2026-0001',
                    'issuer' => (string) __('Demo-Prüfstelle GmbH'),
                    'issued_on' => now()->toDateString(),
                    'valid_until' => now()->addYear()->toDateString(),
                ],
            ]);

            return 1;
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::info('Demo-Seeder: Prüfmittel-Demo übersprungen: ' . $e->getMessage());

            return 0;
        }
    }

    /** Demo Feature 071: E/S/G-Kriterien, Stromverbrauch + Gerätebewertung. */
    public function seedSustainability(Organization $organization, ?User $actor): int {
        if ($actor === null) {
            return 0;
        }

        try {
            foreach ([['environment', 'Energieeffizienz', 3], ['environment', 'Reparierbarkeit', 2], ['social', 'Arbeitsschutz beim Einsatz', 2], ['governance', 'Lieferantennachweise', 1]] as [$dimension, $label, $weight]) {
                \App\Models\Sustainability\SustainabilityCriterion::query()->firstOrCreate([
                    'organization_id' => $organization->id,
                    'dimension' => $dimension,
                    'label' => $label,
                ], ['weight' => $weight, 'active' => true]);
            }

            \App\Models\Sustainability\SustainabilityActivityRecord::query()->create([
                'organization_id' => $organization->id,
                'activity_code' => 'electricity_kwh',
                'amount' => '1250.000',
                'unit' => 'kWh',
                'period_start' => \Carbon\Carbon::now()->startOfQuarter()->toDateString(),
                'period_end' => \Carbon\Carbon::now()->toDateString(),
                'data_quality' => 'measured',
                'source_note' => (string) __('Zählerstand Hauptgebäude (Demo)'),
                'created_by' => $actor->id,
            ]);

            $assessments = app(\App\Services\Sustainability\SustainabilityAssessmentService::class);
            $assessment = $assessments->createDraft($organization->id, null, null, (string) __('Akkuschrauber-Flotte (Demo)'), $actor);
            foreach ($assessment->items as $index => $item) {
                $item->update(['score' => [4, 3, 5, 2][$index % 4], 'data_quality' => 'calculated', 'source_note' => (string) __('Herstellerangaben + Wartungshistorie (Demo)')]);
            }
            $assessments->finalize($assessment->refresh(), $actor);

            return 1;
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::info('Demo-Seeder: Nachhaltigkeits-Demo übersprungen: ' . $e->getMessage());

            return 0;
        }
    }

    /**
     * IT-Demoszenario Helpdesk (Feature 065, P10): Portal-Queue + Incident
     * mit Konversation und Wartezustand, gelöstes Ticket mit Bewertung,
     * Problem aus Incidents, freigegebene Standard-Change-Vorlage + Change.
     *
     * @param Collection<int, User> $users
     */
    public function seedHelpdesk(Organization $organization, Customer $customer, Collection $users): int {
        if (\App\Models\ServiceQueue::query()->where('organization_id', $organization->id)->exists()) {
            return 0;
        }
        /** @var User $agent */
        $agent = $users->first();

        $queue = \App\Models\ServiceQueue::query()->create([
            'organization_id' => $organization->id,
            'name' => 'IT-Support',
            'purpose' => 'Zentrale Anlaufstelle für Störungen und Anfragen.',
            'is_default' => true,
            'visibility' => 'portal',
        ]);

        $tickets = app(\App\Services\ServiceTicket\ServiceTicketService::class);
        $conversation = app(\App\Services\ServiceTicket\TicketConversationService::class);

        // Incident mit Konversation + Wartezustand.
        $incident = $tickets->create($organization, $agent, [
            'title' => 'VPN bricht mehrmals täglich ab',
            'description' => 'Mehrere Nutzer melden Abbrüche seit dem letzten Update.',
            'kind' => 'incident',
            'queue_id' => $queue->id,
            'customer_id' => $customer->id,
        ]);
        $tickets->assign($incident, $agent, $agent->id);
        $conversation->reply($incident->fresh() ?? $incident, $agent, 'Wir haben das Problem reproduziert und analysieren die Ursache.');
        $conversation->note($incident->fresh() ?? $incident, $agent, 'Verdacht: MTU-Problem nach Firmware 2.4.1.');

        // Gelöstes zweites Ticket.
        $solved = $tickets->create($organization, $agent, [
            'title' => 'Neuer Arbeitsplatz für Auszubildende',
            'kind' => 'service_request',
            'queue_id' => $queue->id,
            'customer_id' => $customer->id,
        ]);
        $tickets->assign($solved, $agent, $agent->id);
        $solved = $tickets->transition($solved->fresh() ?? $solved, $agent, \App\Enums\ServiceTicket\ServiceTicketStatus::InProgress);
        $solved = $tickets->transition($solved, $agent, \App\Enums\ServiceTicket\ServiceTicketStatus::Done);

        // Problem aus dem Incident + freigegebene Standard-Change-Vorlage + Change.
        $problem = app(\App\Services\ServiceTicket\ProblemService::class)
            ->openFromIncidents([$incident->fresh() ?? $incident], 'Wiederkehrende VPN-Abbrüche nach Firmware-Update', $agent);
        app(\App\Services\ServiceTicket\ProblemService::class)->transition($problem, 'analyzing', $agent);

        $template = \App\Models\ChangeTemplate::query()->create([
            'organization_id' => $organization->id,
            'name' => 'Firmware-Rollout Netzwerkgeräte',
            'implementation_plan' => 'Staging → Pilotgruppe → Flächenrollout.',
            'test_plan' => 'VPN-Dauerlast über 24h.',
            'rollback_plan' => 'Firmware-Downgrade auf 2.3.9.',
            'approved' => true,
        ]);
        app(\App\Services\ServiceTicket\ChangeService::class)->submit([
            'title' => 'Firmware-Downgrade VPN-Gateways',
            'change_type' => 'standard',
            'reason' => 'Behebt die VPN-Abbrüche (Problem-Analyse).',
            'problem_id' => $problem->id,
        ], $agent, [], $template);

        return \App\Models\ServiceTicket::query()->where('organization_id', $organization->id)->count();
    }

    /**
     * Agile Vorführ-Boards (Feature 064, P7): Projekt 1 als Scrum-Board mit
     * abgeschlossenem und aktivem Sprint samt mehrwöchiger Event-Historie
     * (Burndown/Velocity/CFD-Demos), Projekt 2 als Kanban-Board mit
     * WIP-Limit und Blockierung. Rückdatierung via Carbon::setTestNow —
     * im finally IMMER zurückgesetzt.
     *
     * @param Collection<int, Project> $projects
     * @param Collection<int, User> $users
     */
    public function seedAgileBoards(Collection $projects, Collection $users): int {
        $scrumProject = $projects->get(0);
        if ($scrumProject === null || \App\Models\Agile\AgileBoard::query()->where('project_id', $scrumProject->id)->exists()) {
            return 0;
        }
        $kanbanProject = $projects->get(1);

        $boards = app(\App\Services\Agile\AgileBoardService::class);
        $items = app(\App\Services\Agile\AgileWorkItemService::class);
        $sprints = app(\App\Services\Agile\AgileSprintService::class);
        /** @var User $actor */
        $actor = $users->first();
        $base = \Illuminate\Support\Carbon::now()->subWeeks(4)->startOfWeek()->setTime(9, 0);
        $at = fn(int $days, int $hour = 9) => \Illuminate\Support\Carbon::setTestNow($base->copy()->addDays($days)->setTime($hour, 0));

        try {
            // ── Scrum-Board mit zwei Sprints ─────────────────────────────
            $at(0);
            $board = $boards->activate($scrumProject, \App\Models\Agile\AgileBoard::METHOD_SCRUM, $actor);
            $inProgress = $board->columns()->where('name', 'In Arbeit')->firstOrFail();
            $done = $board->columns()->where('category', 'done')->firstOrFail();

            $stories = collect([
                ['Anmeldung mit Zwei-Faktor absichern', 5],
                ['Dashboard-Kacheln konfigurierbar machen', 3],
                ['Export nach XLSX bereitstellen', 8],
                ['Benachrichtigungen zusammenfassen', 2],
                ['Suche über alle Bereiche', 5],
                ['Mobile Ansicht für die Zeiterfassung', 3],
            ])->map(fn(array $row) => $items->create($board, [
                'title' => $row[0],
                'story_points' => $row[1],
            ], $actor))->values()->all();

            $sprintOne = $sprints->plan($board, [
                'name' => 'Sprint 1', 'goal' => 'Grundfunktionen lieferfähig machen',
                'starts_on' => $base->toDateString(), 'ends_on' => $base->copy()->addDays(11)->toDateString(),
            ], $actor);
            foreach (array_slice($stories, 0, 4) as $story) {
                $sprints->assign($sprintOne, $story, $actor);
            }
            $at(0, 10);
            $sprintOne = $sprints->start($sprintOne, $actor);

            $move = function (int $index, $column, int $day, int $hour = 9) use ($boards, $stories, $actor, $at): void {
                $at($day, $hour);
                $item = $stories[$index]->fresh() ?? $stories[$index];
                $boards->move($item, $column, (int) $item->lock_version, null, $actor);
            };
            $move(0, $inProgress, 2);
            $move(0, $done, 4);
            $move(1, $inProgress, 5);
            $move(1, $done, 7);
            $move(2, $inProgress, 8);

            $at(10);
            $sprintTwo = $sprints->plan($board, [
                'name' => 'Sprint 2', 'goal' => 'Auswertung und Suche ausbauen',
                'starts_on' => $base->copy()->addDays(14)->toDateString(),
                'ends_on' => $base->copy()->addDays(31)->toDateString(),
            ], $actor);

            $at(11, 16);
            $sprints->complete($sprintOne->fresh() ?? $sprintOne, [
                (int) $stories[2]->id => (string) $sprintTwo->id, // Carry-over in Sprint 2
                (int) $stories[3]->id => 'backlog',
            ], $actor);

            $sprintTwo = $sprintTwo->fresh() ?? $sprintTwo;
            $sprints->assign($sprintTwo, $stories[4], $actor);
            $at(14);
            $sprints->start($sprintTwo, $actor);
            $move(2, $inProgress, 15);
            $move(4, $inProgress, 16);
            $at(17);
            $boards->block($stories[2]->fresh() ?? $stories[2], 'Warten auf Kundenfreigabe', $actor);
            $at(18, 14);
            $boards->unblock($stories[2]->fresh() ?? $stories[2], $actor);
            $move(2, $done, 19);
            $at(20);
            $boards->block($stories[4]->fresh() ?? $stories[4], 'Testumgebung nicht erreichbar', $actor);

            // ── Kanban-Board mit WIP-Limit ───────────────────────────────
            if ($kanbanProject === null) {
                return 1;
            }
            $at(3);
            $kanban = $boards->activate($kanbanProject, \App\Models\Agile\AgileBoard::METHOD_KANBAN, $actor);
            $kanbanProgress = $kanban->columns()->where('name', 'In Arbeit')->firstOrFail();
            $boards->saveColumn($kanban, [
                'name' => (string) $kanbanProgress->name,
                'category' => 'in_progress',
                'wip_limit' => 2,
                'position' => (int) $kanbanProgress->position,
            ], $kanbanProgress, $actor);
            $kanbanDone = $kanban->columns()->where('category', 'done')->firstOrFail();

            $tasks = collect([
                'Serverwartung Standort Nord', 'Zertifikate erneuern',
                'Backup-Konzept prüfen', 'Monitoring-Alarme entrümpeln',
            ])->map(fn(string $title) => $items->create($kanban, ['title' => $title, 'item_type' => 'task'], $actor))->values()->all();

            $kanbanMove = function (int $index, $column, int $day) use ($boards, $tasks, $actor, $at): void {
                $at($day, 11);
                $item = $tasks[$index]->fresh() ?? $tasks[$index];
                $boards->move($item, $column, (int) $item->lock_version, null, $actor);
            };
            $kanbanMove(0, $kanbanProgress, 4);
            $kanbanMove(0, $kanbanDone, 6);
            $kanbanMove(1, $kanbanProgress, 7);
            $kanbanMove(2, $kanbanProgress, 12);

            return 2;
        } finally {
            \Illuminate\Support\Carbon::setTestNow();
        }
    }

    /**
     * Demodaten der fünf Phase-38-Pakete (Vollaudit 2026-07, N23):
     * Urlaubsanspruch mit Übertrag, Kassenbuch mit Einträgen + Tagesabschluss,
     * aktiver Abrechnungsplan, Rechnungsentwurf mit Rabatt/Skonto,
     * Führerscheinkontrolle. Jeder Block ist einzeln robust — ein Fehler
     * (z. B. deaktiviertes Modul) bricht den Gesamt-Seed nicht ab.
     *
     * @param  Collection<int, User>  $users
     */
    public function seedPhase38Basics(Organization $organization, Customer $customer, Collection $users): int {
        /** @var User|null $actor */
        $actor = $users->first();
        if ($actor === null) {
            return 0;
        }
        $count = 0;

        // 1) Urlaubsanspruch mit Übertrag aus dem Vorjahr (Verfall 31.03.).
        try {
            \App\Models\VacationEntitlement::query()->firstOrCreate([
                'organization_id' => $organization->id,
                'user_id' => $actor->id,
                'year' => (int) \Illuminate\Support\Carbon::now()->year,
            ], [
                'entitled_days' => 30,
                'carryover_days' => 5,
                'carryover_expires_on' => \Illuminate\Support\Carbon::now()->startOfYear()->addMonths(3)->subDay()->toDateString(),
                'note' => (string) __('Demo: Resturlaub aus dem Vorjahr, verfällt zum 31.03.'),
            ]);
            $count++;
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::info('Demo-Seeder: Urlaubsanspruch übersprungen: ' . $e->getMessage());
        }

        // 2) Barkasse mit zwei Buchungen und Tagesabschluss (MVP-414).
        try {
            $register = \App\Models\CashRegister::query()->firstOrCreate([
                'organization_id' => $organization->id,
                'name' => (string) __('Demo-Barkasse'),
            ], [
                'currency' => 'EUR',
                'opening_balance' => '150.00',
                'opened_on' => \Illuminate\Support\Carbon::now()->subDays(10)->toDateString(),
                'active' => true,
            ]);
            if ($register->wasRecentlyCreated) {
                $cash = app(\App\Services\Finance\CashBookService::class);
                $bookedOn = \Illuminate\Support\Carbon::now()->subDay();
                $cash->record($register, [
                    'booked_on' => $bookedOn->toDateString(),
                    'direction' => \App\Models\CashEntry::DIRECTION_IN,
                    'amount' => 250.00,
                    'purpose' => (string) __('Barverkauf Kleinmaterial (Demo)'),
                    'tax_rate' => 19,
                    'created_by' => $actor->id,
                ]);
                $cash->record($register, [
                    'booked_on' => $bookedOn->toDateString(),
                    'direction' => \App\Models\CashEntry::DIRECTION_OUT,
                    'amount' => 40.00,
                    'purpose' => (string) __('Büromaterial (Demo)'),
                    'tax_rate' => 19,
                    'created_by' => $actor->id,
                ]);
                $cash->closeDay($register, $bookedOn, $cash->balanceAsOf($register, $bookedOn), (string) __('Demo-Tagesabschluss'), $actor->id);
            }
            $count++;
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::info('Demo-Seeder: Kassenbuch übersprungen: ' . $e->getMessage());
        }

        // 3) Aktiver Abrechnungsplan (MVP-415) — monatliche Wartungspauschale.
        try {
            $schedule = \App\Models\InvoiceSchedule::query()->firstOrCreate([
                'organization_id' => $organization->id,
                'customer_id' => $customer->id,
                'title' => (string) __('Wartungspauschale monatlich (Demo)'),
            ], [
                'interval_unit' => \App\Models\InvoiceSchedule::UNIT_MONTH,
                'interval_count' => 1,
                'billing_period_mode' => 'previous',
                'next_run_on' => \Illuminate\Support\Carbon::now()->addMonth()->startOfMonth()->toDateString(),
                'status' => \App\Models\InvoiceSchedule::STATUS_ACTIVE,
                'created_by' => $actor->id,
            ]);
            if ($schedule->wasRecentlyCreated) {
                $schedule->items()->create([
                    'organization_id' => $organization->id,
                    'position' => 1,
                    'description' => (string) __('Wartungspauschale {zeitraum}'),
                    'quantity' => '1',
                    'unit' => (string) __('Pauschale'),
                    'unit_price' => '190.00',
                    'tax_rate' => 19,
                ]);
            }
            $count++;
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::info('Demo-Seeder: Abrechnungsplan übersprungen: ' . $e->getMessage());
        }

        // 4) Rechnungsentwurf mit Positionsrabatt und Skonto (MVP-416).
        try {
            $invoice = Invoice::create([
                'organization_id' => $organization->id,
                'customer_id' => $customer->id,
                'number' => app(\App\Services\Invoicing\InvoiceGenerator::class)->nextNumber($organization->id),
                'status' => Invoice::STATUS_DRAFT,
                'currency' => $customer->currency,
                'tax_rate' => 19,
                'skonto_percent' => '2.00',
                'skonto_days' => 10,
                'notes' => (string) __('Demo: Rechnung mit Positionsrabatt und Skonto (2 % bei Zahlung in 10 Tagen).'),
                'created_by' => $actor->id,
            ]);
            $invoice->items()->create([
                'organization_id' => $organization->id,
                'service_date' => \Illuminate\Support\Carbon::now()->toDateString(),
                'description' => (string) __('Serviceeinsatz vor Ort (Demo)'),
                'quantity' => '3',
                'unit' => (string) __('invoicing.unit_hour'),
                'unit_price' => '95.00',
                'discount_percent' => '10.00',
                'tax_rate' => 19,
                'position' => 1,
            ]);
            $count++;
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::info('Demo-Seeder: Rabatt/Skonto-Rechnung übersprungen: ' . $e->getMessage());
        }

        // 5) Führerscheinkontrolle (Fuhrpark, Halterhaftung).
        try {
            /** @var User $driver */
            $driver = $users->skip(1)->first() ?? $actor;
            \App\Models\DriverLicenseCheck::query()->firstOrCreate([
                'organization_id' => $organization->id,
                'user_id' => $driver->id,
            ], [
                'checked_by' => $actor->id,
                'checked_at' => \Illuminate\Support\Carbon::now()->toDateString(),
                'license_classes' => 'B, BE',
                'license_valid_until' => \Illuminate\Support\Carbon::now()->addYears(3)->toDateString(),
                'next_due_on' => \Illuminate\Support\Carbon::now()->addMonths(6)->toDateString(),
                'note' => (string) __('Demo: Sichtkontrolle Original-Führerschein.'),
            ]);
            $count++;
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::info('Demo-Seeder: Führerscheinkontrolle übersprungen: ' . $e->getMessage());
        }

        return $count;
    }
}
