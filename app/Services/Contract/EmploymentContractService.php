<?php
/*
 * Created on   : Sun Sep 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : EmploymentContractService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Contract;

use App\Enums\Contract\{ContractKind, ContractPartnerType, ContractTermKind, IndexationMethod, SignatureParty};
use App\Models\Applications\JobApplication;
use App\Models\Contract\Contract;
use App\Models\Document\Document;
use App\Models\Platform\{Organization, User};
use App\Services\Document\DocumentService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Arbeitsvertrag zur Unterschrift (MVP-939): Vertrag der Art „Arbeitsvertrag“
 * für ein Teammitglied oder eine Bewerbung, Fassung über die Signaturschicht
 * aus Feature 157, Link an die Person; die Organisation zeichnet gegen.
 */
final class EmploymentContractService {
    public function __construct(
        private readonly ContractService $contracts,
        private readonly ContractSigningService $signing,
        private readonly DocumentService $documents,
    ) {}

    /** @param array{title: string, starts_on: string, email: string, declaration_text: string} $data */
    public function create(Organization $organization, User $actor, User|JobApplication $person, array $data, UploadedFile $file): Contract {
        $name = $person instanceof User ? (string) $person->name : (string) $person->candidate_name;
        if (trim($data['email']) === '') {
            throw ValidationException::withMessages(['email' => __('hr.employment.error.email')]);
        }

        return DB::transaction(function () use ($organization, $actor, $person, $data, $file, $name): Contract {
            $contract = $this->contracts->create($organization, $actor, [
                'title' => $data['title'],
                'kind' => ContractKind::Employment->value,
                'partner_type' => ContractPartnerType::Other->value,
                'partner_name' => $name,
                'employee_user_id' => $person instanceof User ? $person->id : null,
                'job_application_id' => $person instanceof JobApplication ? $person->id : null,
                'term_kind' => ContractTermKind::OpenEnded->value,
                'starts_on' => $data['starts_on'],
                'indexation_method' => IndexationMethod::None->value,
                'responsible_user_id' => $actor->id,
            ]);
            /** @var Document $document */
            $document = $this->documents->create($contract, $actor, ['title' => $data['title'], 'document_type' => 'contract', 'confidential' => true], $file);
            $revision = $this->signing->createRevision($contract, $actor, [
                'contract_version_id' => (int) $document->current_version_id,
                'attachment_version_ids' => [],
                'controller_party' => SignatureParty::Organization->value,
                'declaration_text' => $data['declaration_text'],
                'customer' => ['signer_name' => $name, 'signer_function' => null, 'signer_email' => $data['email']],
                'organization' => ['required' => true, 'signer_name' => (string) $actor->name, 'signer_function' => null, 'signer_email' => null],
            ]);
            $this->signing->prepare($revision, $actor);
            $this->signing->sendSignLink($revision->customerRequest()->firstOrFail(), $actor);

            return $contract;
        });
    }
}
