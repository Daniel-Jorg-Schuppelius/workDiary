<?php
/*
 * Created on   : Tue May 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : audit-events.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

/*
 * Lesbare Bezeichnungen für Audit-Events (audit_logs.event). Punktnotation
 * wird als verschachteltes Array abgelegt; AuditLog::eventLabel() fällt auf
 * den rohen Event-String zurück, wenn ein Schlüssel fehlt. Vollständigkeit
 * erzwingt Tests\Feature\AuditTranslationCoverageTest.
 */
return [
    'expense' => [
        'voucher_pushed' => 'Expense pushed to accounting as a voucher',
        'voucher_linked' => 'Expense linked to accounting voucher',
        'voucher_unlinked' => 'Link to accounting voucher removed',
    ],
    'agile' => [
        'board' => [
            'activated' => 'Agile board activated',
            'column_deleted' => 'Board column deleted',
            'column_saved' => 'Board column saved',
            'settings_updated' => 'Board settings updated',
        ],
        'sprint' => [
            'planned' => 'Sprint planned',
        ],
    ],
    'ai' => [
        'invoked' => 'AI invoked',
        'suggestion_decided' => 'AI suggestion decided',
    ],
    'archived' => 'Archived',
    'asset' => [
        'blockExceptionGranted' => 'Asset block exception granted',
        'blockExceptionRevoked' => 'Asset block exception revoked',
        'blocked' => 'Asset blocked',
        'checkedIn' => 'Asset checked in',
        'checkedOut' => 'Asset checked out',
        'created' => 'Asset created',
        'decommissioned' => 'Asset decommissioned',
        'defectReported' => 'Asset defect reported',
        'defectUpdated' => 'Asset defect updated',
        'moved' => 'Asset moved',
        'ownershipTransferred' => 'Asset ownership transferred',
        'ownership_changed' => 'Asset ownership changed',
        'statusChanged' => 'Asset status changed',
        'unblocked' => 'Asset unblocked',
        'updated' => 'Asset updated',
        'useBlockedByGuard' => 'Asset use blocked by guard',
    ],
    'assetAssignment' => [
        'checkedIn' => 'Asset assignment: checked in',
        'checkedOut' => 'Asset assignment: checked out',
    ],
    'assetCompliance' => [
        'assigned' => 'Compliance profile assigned',
        'claimOpened' => 'Compliance: claim opened',
        'decommissionRequested' => 'Decommission requested',
        'inspected' => 'Asset inspected',
        'restrictedUse' => 'Use restricted',
    ],
    'assetDefect' => [
        'reported' => 'Defect reported',
        'statusChanged' => 'Defect status changed',
    ],
    'assetFinance' => [
        'activated' => 'Financing activated',
        'closed' => 'Financing closed',
        'contractLinked' => 'Finance contract linked',
        'deadlineMissed' => 'Finance deadline missed',
        'ended' => 'Financing ended',
        'optionExercised' => 'Finance option exercised',
        'rateLinked' => 'Finance rate linked',
        'terminated' => 'Financing terminated',
        'usageRecorded' => 'Usage recorded',
    ],
    'auth' => [
        'failed' => 'Failed login',
        'login' => 'Login',
        'logout' => 'Logout',
        'password_reset' => 'Password reset',
    ],
    'b2b_catalog' => [
        'access_issued' => 'B2B catalog access issued',
        'access_revoked' => 'B2B catalog access revoked',
        'access_rotated' => 'B2B catalog access rotated',
        'item_released' => 'B2B catalog item released',
        'item_removed' => 'B2B catalog item removed',
    ],
    'b2b_order' => [
        'booked' => 'B2B order booked',
        'dismissed' => 'B2B order dismissed',
        'received' => 'B2B order received',
    ],
    'backup' => [
        'checkRestore' => 'Backup restore checked',
        'completed' => 'Backup completed',
        'generationPurged' => 'Backup generation purged',
        'heartbeatReceived' => 'Backup heartbeat received',
        'holdSet' => 'Backup hold set',
        'masterKeyGenerated' => 'Backup master key generated',
        'recoveryKeyGenerated' => 'Backup recovery key generated',
        'restoreTested' => 'Restore test performed',
        'retentionDeleted' => 'Backup deleted per retention',
        'tokenRotated' => 'Backup token rotated',
        'verified' => 'Backup verified',
    ],
    'backupTarget' => [
        'connected' => 'Backup target connected',
        'disconnected' => 'Backup target disconnected',
        'scopeBlocked' => 'Backup target: scope blocked',
    ],
    'branch_profile' => [
        'installed' => 'Branch profile installed',
    ],
    'caldav' => [
        'connection_saved' => 'CalDAV connection saved',
        'disconnected' => 'CalDAV disconnected',
        'publish_manual' => 'CalDAV published manually',
    ],
    'calendly' => [
        'booking_link_created' => 'Calendly booking link created',
        'cancel_synced' => 'Calendly cancellation synced',
        'subscribed' => 'Calendly webhook subscribed',
    ],
    'carddav' => [
        'addressbook_chosen' => 'CardDAV address book chosen',
        'connection_saved' => 'CardDAV connection saved',
        'disconnected' => 'CardDAV disconnected',
        'sync_manual' => 'CardDAV synced manually',
    ],
    'change' => [
        'asset_attached' => 'Change: asset attached',
        'asset_detached' => 'Change: asset detached',
        'completed' => 'Change completed',
        'decided' => 'Change decided',
        'implementing' => 'Change in implementation',
        'submitted' => 'Change submitted',
        'tickets_linked' => 'Change: tickets linked',
    ],
    'change_template' => [
        'approved' => 'Change template approved',
        'created' => 'Change template created',
        'updated' => 'Change template updated',
    ],
    'chat' => [
        'disconnected' => 'Chat integration disconnected',
        'webhook_created' => 'Chat webhook created',
    ],
    'classification' => [
        'imported' => 'Classification imported',
        'requirementMissing' => 'Classification requirement missing',
        'sortChanged' => 'Classification sort order changed',
    ],
    'cloudIntake' => [
        'connected' => 'Cloud document intake connected',
        'disconnected' => 'Cloud document intake disconnected',
        'folderSelected' => 'Cloud intake folder selected',
    ],
    'communication' => [
        'confidential' => [
            'set' => 'Communication marked confidential',
            'unset' => 'Confidentiality removed',
            'viewed' => 'Confidential communication viewed',
        ],
        'deleted' => 'Communication note deleted',
        'followup' => [
            'completed' => 'Follow-up completed',
        ],
        'published' => 'Communication note published',
    ],
    'compliance' => [
        'finding' => [
            'accepted' => 'Violation accepted',
            'acknowledged' => 'Violation acknowledged',
            'detected' => 'Violation detected',
            'reopened' => 'Violation recurred',
            'resolved' => 'Violation resolved',
        ],
    ],
    'contract' => [
        'activated' => 'Contract activated',
        'approved_step' => 'Contract approval step granted',
        'cancelled' => 'Contract cancelled',
        'concluded' => 'Contract concluded',
        'ended' => 'Contract ended',
        'negotiation_opened' => 'Contract negotiation opened',
        'obligationAdded' => 'Contract obligation added',
        'obligationCompleted' => 'Contract obligation completed',
        'obligationMissed' => 'Contract obligation missed',
        'review_item_added' => 'Contract review item added',
        'review_item_resolved' => 'Contract review item resolved',
        'terminated' => 'Contract terminated',
        'version_added' => 'Contract version added',
    ],
    'correction' => [
        'applyFailed' => 'Correction apply failed',
    ],
    'created' => 'Created',
    'crisis' => [
        'activated' => 'Crisis case activated',
        'alert_acknowledged' => 'Crisis alert acknowledged',
        'alert_escalated' => 'Crisis alert escalated',
        'alerted' => 'Crisis alert raised',
        'all_clear' => 'All clear given',
        'closed' => 'Crisis case closed',
        'communication_approved' => 'Crisis communication approved',
        'communication_sent' => 'Crisis communication sent',
        'exercise_documented' => 'Crisis exercise documented',
        'linked' => 'Crisis case linked',
        'reported' => 'Crisis case reported',
        'reviewed' => 'Crisis review completed',
        'team_assigned' => 'Crisis team member assigned',
        'team_removed' => 'Crisis team member removed',
    ],
    'cti' => [
        'connection_issued' => 'CTI connection issued',
        'disconnected' => 'CTI disconnected',
    ],
    'datanorm' => [
        'exported' => 'DATANORM file exported',
    ],
    'dayClose' => [
        'closed' => 'Day closed',
        'correctionApproved' => 'Day correction approved',
        'correctionRejected' => 'Day correction rejected',
        'correctionRequested' => 'Day correction requested',
        'entrySaved' => 'Daily close saved',
        'opened' => 'Daily close opened',
        'reopened' => 'Day reopened',
    ],
    'deleted' => 'Deleted',
    'demo' => [
        'orgCreated' => 'Demo tenant created',
        'reset' => 'Demo tenant reset',
        'seeded' => 'Demo data seeded',
    ],
    'device' => [
        'revoked' => 'Device revoked',
    ],
    'diagnostics' => [
        'testTriggered' => 'Diagnostics test triggered',
        'viewed' => 'Diagnostics viewed',
    ],
    'document' => [
        'archived' => 'Document archived',
        'confidentialAccessed' => 'Confidential document accessed',
        'deleted' => 'Document deleted',
        'einvoice_received' => 'E-invoice received',
        'einvoice_xml_exported' => 'E-invoice XML exported',
        'released_to_customer' => 'Document released to customer',
        'revoked_from_customer' => 'Customer release revoked',
        'version' => [
            'added' => 'Document version added',
        ],
    ],
    'export' => [
        'deleted' => 'Export deleted',
    ],
    'external' => [
        'participant' => [
            'invited' => 'External participant invited',
            'revoked' => 'External participant revoked',
        ],
    ],
    'finance' => [
        'datev' => [
            'debtors_exported' => 'DATEV debtors exported',
            'gl_accounts_exported' => 'DATEV G/L accounts exported',
        ],
    ],
    'form' => [
        'submitted' => 'Form submitted',
        'template' => [
            'activated' => 'Form template activated',
            'archived' => 'Form template archived',
            'deleted' => 'Form template deleted',
        ],
    ],
    'gobd' => [
        'exported' => 'GoBD export created',
    ],
    'google_calendar' => [
        'calendar_selected' => 'Google calendar selected',
        'publish_manual' => 'Google calendar published manually',
    ],
    'idea_map' => [
        'archived' => 'Idea map archived',
        'exported' => 'Idea map exported',
        'ownership_transferred' => 'Idea map transferred',
        'share_granted' => 'Idea map shared',
        'share_revoked' => 'Idea map share revoked',
        'synced' => 'Idea map synced',
        'unarchived' => 'Idea map unarchived',
    ],
    'idea_node' => [
        'converted' => 'Idea node converted',
        'moved' => 'Idea node moved',
    ],
    'import' => [
        'confirmed' => 'Import confirmed',
        'finished' => 'Import finished',
        'partial' => 'Import partially finished',
        'preflightFailed' => 'Import preflight failed',
        'started' => 'Import started',
    ],
    'incoming_einvoice' => [
        'decided' => 'Incoming invoice decided',
        'transferred' => 'Incoming invoice transferred',
    ],
    'integration' => [
        'changed' => 'Integration enabled/disabled',
        'data_ownership_changed' => 'Data ownership changed',
        'inbox_resolved' => 'Inbox item resolved',
        'settings_changed' => 'Integration settings changed',
    ],
    'integrity' => [
        'check' => 'Integrity check performed',
        'freeze' => 'Integrity baseline frozen',
        'lockdown_engaged' => 'Integrity lockdown engaged',
        'lockdown_released' => 'Integrity lockdown released',
    ],
    'inventory' => [
        'mode_changed' => 'Inventory mode changed',
        'negativeApproved' => 'Negative stock approved',
    ],
    'investment' => [
        'budget_approved' => 'Investment budget approved',
        'budget_rejected' => 'Investment budget rejected',
        'budget_submitted' => 'Investment budget submitted',
        'budget_supplement' => 'Supplementary budget requested',
        'created' => 'Investment case created',
        'deviation_decided' => 'Investment deviation decided',
        'linked' => 'Investment linked',
        'option_recommended' => 'Investment option recommended',
        'reviewed' => 'Investment reviewed',
    ],
    'invoice' => [
        'approved' => 'Invoice approved',
        'dunned' => 'Invoice dunned',
        'document_imported' => 'Invoice file imported',
        'einvoice_exported' => 'E-invoice exported',
        'einvoice_options_updated' => 'E-invoice options updated',
        'import_review_confirmed' => 'Import review confirmed',
        'objectionDocumented' => 'Invoice objection documented',
        'proforma_converted' => 'Proforma converted to invoice',
    ],
    'isms' => [
        'audit' => [
            'deleted' => 'ISMS audit deleted',
            'transitioned' => 'ISMS audit: status changed',
        ],
        'audit_package' => [
            'created' => 'Audit package created',
            'finalized' => 'Audit package finalized',
            'token_created' => 'Audit package token created',
            'token_revoked' => 'Audit package token revoked',
        ],
        'audit_program' => [
            'audit_attached' => 'Audit attached to program',
            'created' => 'Audit program created',
            'deleted' => 'Audit program deleted',
            'status_changed' => 'Audit program: status changed',
        ],
        'certificate' => [
            'added' => 'Certificate added',
        ],
        'control' => [
            'deleted' => 'Control deleted',
        ],
        'corrective_action' => [
            'deleted' => 'Corrective action deleted',
            'transitioned' => 'Corrective action: status changed',
        ],
        'finding' => [
            'deleted' => 'Finding deleted',
            'reverted_to_correction' => 'Finding reverted to correction',
            'transitioned' => 'Finding: status changed',
        ],
        'management_review' => [
            'approved' => 'Management review approved',
            'deleted' => 'Management review deleted',
        ],
        'norm_status' => [
            'expired' => 'Norm status expired',
            'transitioned' => 'Norm status changed',
        ],
        'requirement' => [
            'deleted' => 'ISMS requirement deleted',
        ],
        'risk' => [
            'deleted' => 'ISMS risk deleted',
            'transitioned' => 'ISMS risk: status changed',
        ],
        'risk_assessment' => [
            'approved' => 'Risk assessment approved',
            'deleted' => 'Risk assessment deleted',
        ],
        'scope' => [
            'deleted' => 'Scope deleted',
        ],
        'security_incident' => [
            'deleted' => 'Security incident deleted',
            'transitioned' => 'Security incident: status changed',
        ],
        'software_installation' => [
            'deleted' => 'Software installation deleted',
        ],
        'software_product' => [
            'deleted' => 'Software product deleted',
        ],
        'supplier_assessment' => [
            'deleted' => 'Supplier assessment deleted',
            'transitioned' => 'Supplier assessment: status changed',
        ],
        'vulnerability' => [
            'deleted' => 'Vulnerability deleted',
            'exploitability_decided' => 'Exploitability decided',
            'transitioned' => 'Vulnerability: status changed',
        ],
    ],
    'key_handover' => [
        'recorded' => 'Key handover recorded',
    ],
    'knowledge' => [
        'archived' => 'Knowledge article archived',
        'deleted' => 'Knowledge article deleted',
        'linked' => 'Knowledge article linked',
        'published' => 'Knowledge article published',
        'unlinked' => 'Knowledge article unlinked',
    ],
    'license' => [
        'installed' => 'License installed',
        'keyIssued' => 'License key issued',
        'orgInstalled' => 'Tenant license installed',
        'orgIssued' => 'Tenant license issued',
        'orgRemoved' => 'Tenant license removed',
        'scopeConfigured' => 'License scope configured',
    ],
    'limit' => [
        'exceeded' => 'Limit exceeded',
    ],
    'mail' => [
        'connection_saved' => 'Mail connection saved',
        'disconnected' => 'Mail connection disconnected',
    ],
    'maintenance_plan' => [
        'completed' => 'Maintenance completed',
        'created' => 'Maintenance plan created',
        'due_detected' => 'Maintenance due',
        'paused' => 'Maintenance plan paused',
        'resumed' => 'Maintenance plan resumed',
    ],
    'meter_reading' => [
        'recorded' => 'Meter reading recorded',
    ],
    'month_closure' => [
        'bundle_exported' => 'Month closure bundle exported',
    ],
    'msgraph' => [
        'admin_consent_granted' => 'Microsoft 365 admin consent granted',
        'calendar_selected' => 'Microsoft 365 calendar selected',
        'publish_manual' => 'Microsoft 365 calendar published manually',
    ],
    'msgraph_mail' => [
        'settings_saved' => 'Microsoft 365 mail settings saved',
        'test_sent' => 'Microsoft 365 test mail sent',
    ],
    'msgraph_tasks' => [
        'link_removed' => 'Task list link removed',
        'link_saved' => 'Task list link saved',
    ],
    'onboarding' => [
        'completed' => 'Onboarding completed',
        'stepCompleted' => 'Onboarding step completed',
        'stepSkipped' => 'Onboarding step skipped',
        'widgetDismissed' => 'Onboarding widget dismissed',
    ],
    'orgamax_account_confirmed' => 'orgaMAX account confirmed',
    'orgamax_callback_processed' => 'orgaMAX callback processed',
    'orgamax_capabilities_updated' => 'orgaMAX capabilities updated',
    'orgamax_disconnected' => 'orgaMAX disconnected',
    'orgamax_intent_started' => 'orgaMAX intent started',
    'orgamax_invoice_convert_requested' => 'orgaMAX: invoice conversion requested',
    'orgamax_invoice_locked' => 'orgaMAX invoice locked',
    'orgamax_invoice_pdf_fetched' => 'orgaMAX invoice PDF fetched',
    'orgamax_invoice_send_requested' => 'orgaMAX invoice dispatch requested',
    'orgamax_payment_requested' => 'orgaMAX payment requested',
    'orgamax_scopes_missing' => 'orgaMAX scopes missing',
    'organization' => [
        'maintenance_toggled' => 'Maintenance mode toggled',
    ],
    'overtime' => [
        'approved' => 'Overtime approved',
        'stage_approved' => 'Overtime: stage approved',
        'withdrawn' => 'Overtime request withdrawn',
    ],
    'passenger' => [
        'concession_created' => 'Concession created',
        'concession_updated' => 'Concession updated',
        'ride_accepted' => 'Ride accepted',
        'ride_anonymized' => 'Ride anonymized',
        'ride_assigned' => 'Ride assigned',
        'ride_closed' => 'Ride closed',
        'ride_completed' => 'Ride completed',
        'ride_return_recorded' => 'Return ride recorded',
        'ride_started' => 'Ride started',
        'ride_transition' => 'Ride: status changed',
        'settlement_cash_posted' => 'Shift settlement: cash posted',
        'settlement_closed' => 'Shift settlement closed',
        'settlement_created' => 'Shift settlement created',
        'settlement_updated' => 'Shift settlement updated',
        'tariff_created' => 'Tariff created',
        'tariff_rule_added' => 'Tariff rule added',
        'tariff_rule_removed' => 'Tariff rule removed',
        'tariff_updated' => 'Tariff updated',
        'vehicle_profile_created' => 'Vehicle profile created',
        'vehicle_profile_updated' => 'Vehicle profile updated',
    ],
    'payroll' => [
        'wage' => [
            'raised_to_minimum' => 'Wage raised to minimum',
        ],
    ],
    'portal' => [
        'access' => [
            'deactivated' => 'Portal access deactivated',
            'invite_accepted' => 'Portal invitation accepted',
            'invite_resent' => 'Portal invitation re-sent',
            'invited' => 'Portal access invited',
            'reactivated' => 'Portal access reactivated',
        ],
        'query' => [
            'withdrawn' => 'Portal query withdrawn',
        ],
        'visibility' => [
            'updated' => 'Portal visibility changed',
        ],
    ],
    'print' => [
        'claim_opened' => 'Print: claim opened',
        'file_bound' => 'Print file bound',
        'files_purged' => 'Print files purged',
        'order_approved' => 'Print order approved',
        'order_cancelled' => 'Print order cancelled',
        'order_issued' => 'Print order issued',
        'order_opened' => 'Print order opened',
        'preflight_overridden' => 'Preflight overridden',
        'preflight_recorded' => 'Preflight recorded',
        'production_resumed' => 'Production resumed',
        'production_started' => 'Production started',
        'quality_checked' => 'Quality checked',
    ],
    'privacy' => [
        'dsr' => [
            'exported' => 'Data subject request exported',
        ],
        'overviewExported' => 'Privacy overview exported',
        'report' => [
            'exported' => 'Privacy report exported',
        ],
        'ropa' => [
            'exported' => 'Records of processing exported',
        ],
    ],
    'problem' => [
        'effectiveness_checked' => 'Effectiveness checked',
        'known_error_published' => 'Known error published',
        'opened' => 'Problem opened',
        'status_changed' => 'Problem: status changed',
        'updated' => 'Problem updated',
    ],
    'protocol' => [
        'signatureLinkOpened' => 'Signature link opened',
        'signatureRequested' => 'Signature requested',
    ],
    'quote' => [
        'accepted' => 'Quote accepted',
        'approved' => 'Quote approved',
        'converted' => 'Quote converted',
        'created' => 'Quote created',
        'rejected' => 'Quote rejected',
        'sent' => 'Quote sent',
        'versioned' => 'Quote version created',
    ],
    'recipe' => [
        'ingredient_allergens' => 'Ingredient allergens maintained',
        'menu_created' => 'Menu created',
        'menu_item_added' => 'Menu item added',
        'menu_item_removed' => 'Menu item removed',
        'profile_saved' => 'Recipe profile saved',
    ],
    'recruiting' => [
        'application_anonymized' => 'Application anonymized',
        'application_decided' => 'Application decided',
        'application_exported' => 'Application exported',
        'application_received' => 'Application received',
        'document_attached' => 'Application document attached',
        'draft_invited' => 'Employee draft invited',
        'onboarding_draft_created' => 'Onboarding draft created',
        'posting_paused' => 'Job posting paused',
        'posting_published' => 'Job posting published',
        'public_application_received' => 'Public application received',
        'requisition_created' => 'Job requisition created',
    ],
    'render_profile_activated' => 'Render profile activated',
    'rental' => [
        'active' => 'Rental active',
        'assetSwapped' => 'Rental asset swapped',
        'cancelled' => 'Rental cancelled',
        'chargeAdded' => 'Rental charge added',
        'chargeCancelled' => 'Rental charge cancelled',
        'chargesInvoiced' => 'Rental charges invoiced',
        'chargesTransferred' => 'Rental charges transferred',
        'claimOpened' => 'Rental claim opened',
        'closed' => 'Rental closed',
        'completed' => 'Rental completed',
        'depositRequested' => 'Deposit requested',
        'depositSettled' => 'Deposit settled',
        'extended' => 'Rental extended',
        'handedOver' => 'Rental asset handed over',
        'handoverConfirmedByCustomer' => 'Handover confirmed by customer',
        'overdue' => 'Rental overdue',
        'profileSaved' => 'Rental profile saved',
        'reserved' => 'Rental reserved',
        'returned' => 'Rental asset returned',
        'termsFrozen' => 'Rental terms frozen',
    ],
    'report' => [
        'exported' => 'Report exported',
        'presenceEmergencyViewed' => 'Emergency attendance list viewed',
    ],
    'reportView' => [
        'created' => 'Report view saved',
        'deleted' => 'Report view deleted',
        'shared' => 'Report view shared',
    ],
    'restored' => 'Restored',
    'retention' => [
        'approved' => 'Retention proposal approved',
        'purged' => 'Data purged per retention policy',
        'rejected' => 'Retention proposal rejected',
    ],
    'role' => [
        'created' => 'Role created',
        'deleted' => 'Role deleted',
        'updated' => 'Role updated',
    ],
    'rules' => [
        'recalculated' => 'Time rule results recalculated',
    ],
    'scheduler' => [
        'testRun' => 'Scheduler test run',
    ],
    'scim' => [
        'group_mapped' => 'SCIM group mapped',
        'token_issued' => 'SCIM token issued',
        'token_revoked' => 'SCIM token revoked',
    ],
    'service_request' => [
        'decided' => 'Service request decided',
        'fulfilled' => 'Service request fulfilled',
        'submitted' => 'Service request submitted',
    ],
    'service_ticket' => [
        'accepted_by_customer' => 'Ticket accepted by customer',
        'assigned' => 'Ticket assigned',
        'closed' => 'Ticket closed',
        'created' => 'Ticket created',
        'linked' => 'Ticket linked',
        'major_cleared' => 'Major incident cleared',
        'major_marked' => 'Marked as major incident',
        'noted' => 'Ticket note recorded',
        'priority_overridden' => 'Priority overridden',
        'reopened' => 'Ticket reopened',
        'replied' => 'Ticket replied',
        'requeued' => 'Ticket requeued',
        'resumed' => 'Ticket resumed',
        'sla_attached' => 'SLA attached',
        'sla_reaction_breached' => 'SLA reaction time breached',
        'sla_resolution_breached' => 'SLA resolution time breached',
        'status_changed' => 'Ticket status changed',
        'waiting' => 'Ticket waiting',
    ],
    'session' => [
        'revoked' => 'Session revoked',
    ],
    'settings' => [
        'exported' => 'Settings exported',
    ],
    'sharepoint' => [
        'mirror_manual' => 'SharePoint mirrored manually',
        'settings_saved' => 'SharePoint settings saved',
        'target_selected' => 'SharePoint target selected',
    ],
    'shiftRotation' => [
        'activated' => 'Shift rotation activated',
        'assigned' => 'Shift rotation assigned',
        'created' => 'Shift rotation created',
        'entriesUpdated' => 'Rotation entries updated',
        'unassigned' => 'Shift rotation unassigned',
    ],
    'shipping' => [
        'connection_saved' => 'Shipping connection saved',
        'disconnected' => 'Shipping connection disconnected',
    ],
    'sla_violation' => [
        'acknowledged' => 'SLA violation acknowledged',
        'detected' => 'SLA violation detected',
    ],
    'softwareInstallation' => [
        'attached' => 'Software installation attached',
        'detached' => 'Software installation detached',
        'updated' => 'Software installation updated',
    ],
    'sso' => [
        'break_glass_changed' => 'Break-glass access changed',
        'break_glass_used' => 'Break-glass access used',
        'connection_removed' => 'SSO connection removed',
        'connection_tested' => 'SSO connection tested',
        'identity_linked' => 'SSO identity linked',
        'login' => 'SSO login',
        'login_rejected' => 'SSO login rejected',
        'user_provisioned' => 'SSO user provisioned',
    ],
    'support' => [
        'access' => [
            'granted' => 'Support access granted',
            'revoked' => 'Support access revoked',
        ],
        'impersonation' => [
            'start' => 'Support impersonation started',
            'stop' => 'Support impersonation stopped',
        ],
        'reportDownloaded' => 'Support report downloaded',
        'reportGenerated' => 'Support report generated',
        'session' => [
            'action' => 'Support session action',
        ],
        'test' => 'Support test',
    ],
    'sustainability' => [
        'assessment_drafted' => 'ESG assessment drafted',
        'assessment_finalized' => 'ESG assessment finalized',
        'assessment_versioned' => 'ESG assessment version created',
    ],
    'sync' => [
        'applied' => 'Offline sync applied',
    ],
    'tax_rule' => [
        'created' => 'Tax rule created',
        'retired' => 'Tax rule retired',
    ],
    'tenant' => [
        'export' => [
            'requested' => 'Tenant export requested',
        ],
        'statusChanged' => 'Tenant status changed',
    ],
    'tender' => [
        'awarded' => 'Contract awarded (X86 imported)',
        'bid_recorded' => 'Bid recorded in the opening result',
        'created' => 'Tender created',
        'decided' => 'Tender decided',
        'go_decided' => 'Go/no-go decided',
        'submitted' => 'Tender submitted',
        'transferred' => 'Tender transferred',
    ],
    'terminal' => [
        'badge_assigned' => 'Badge assigned',
        'badge_revoked' => 'Badge revoked',
        'deactivated' => 'Terminal deactivated',
        'disabled' => 'Terminal disabled',
        'registered' => 'Terminal registered',
        'status_display_toggled' => 'Status display toggled',
        'token_rotated' => 'Terminal token rotated',
        'unknown_badge' => 'Unknown badge',
    ],
    'timeAccount' => [
        'activated' => 'Time account activated',
        'created' => 'Time account created',
        'manualEntry' => 'Time account: manual entry',
        'reversed' => 'Time account entry reversed',
        'ruleAdded' => 'Time account rule added',
        'ruleRemoved' => 'Time account rule removed',
    ],
    'timeCorrection' => [
        'stage_approved' => 'Time correction: stage approved',
    ],
    'timeDimension' => [
        'type_created' => 'Time dimension type created',
        'type_toggled' => 'Time dimension type toggled',
        'value_created' => 'Time dimension value created',
        'value_deleted' => 'Time dimension value deleted',
    ],
    'timeEntry' => [
        'reassigned' => 'Time entry reassigned to another user',
    ],
    'time_entries' => [
        'reopened' => 'Time entries reopened',
    ],
    'todoist' => [
        'collaborator_assigned' => 'Todoist collaborator assigned',
        'collaborator_unassigned' => 'Todoist collaborator unassigned',
        'link_saved' => 'Todoist link saved',
        'link_status' => 'Todoist link status changed',
        'sections_saved' => 'Todoist sections saved',
        'sync_manual' => 'Todoist synced manually',
    ],
    'token' => [
        'revoked' => 'Token revoked',
    ],
    'updated' => 'Updated',
    'user' => [
        'permission' => [
            'granted' => 'Permission granted',
            'revoked' => 'Permission revoked',
        ],
        'role' => [
            'assigned' => 'Role assigned',
            'revoked' => 'Role revoked',
        ],
        'sessions' => [
            'revoked_all' => 'All sessions revoked',
        ],
    ],
    'user_group' => [
        'member_added' => 'User group: member added',
        'member_removed' => 'User group: member removed',
    ],
    'webdav' => [
        'connection_saved' => 'WebDAV connection saved',
        'disconnected' => 'WebDAV disconnected',
        'mirror_manual' => 'WebDAV mirrored manually',
    ],
    'zammad' => [
        'connection_saved' => 'Zammad connection saved',
        'disconnected' => 'Zammad disconnected',
        'sync_manual' => 'Zammad synced manually',
        'ticket_target_switched' => 'Zammad ticket target switched',
    ],
    'customer' => [
        'design_profile_assigned' => 'Customer design profile assigned/changed',
    ],
];
