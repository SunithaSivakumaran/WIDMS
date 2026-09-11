<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(403); exit("CLI only.\n"); }
require_once __DIR__.'/../config/database.php';

$files=[
 'schema.sql','migration_registration_requests.sql','migration_stock_receiving.sql',
 'migration_supplier_workflow.sql','migration_correction_requests.sql','migration_user_profiles.sql',
 'migration_activity_log.sql','migration_master_data.sql','migration_registration_geography.sql','migration_sso_assignment_management.sql','migration_beneficiaries.sql',
 'migration_goods_workflow.sql','migration_aid_distribution.sql','migration_special_workflows.sql',
 'migration_remove_lifecycle_status.sql','migration_er_alignment.sql','migration_contact_lens_bulk_workflow.sql','migration_southern_geography.sql','migration_galle_service_divisions.sql','migration_aid_request_drafts.sql','migration_optional_beneficiary_nic.sql','migration_vision_camp_delivery_workflow.sql','migration_disability_types.sql',
 'migration_beneficiary_identification.sql',
 'migration_registration_geography.sql',
 'migration_item_eligibility_redesign.sql',
 'migration_clear_default_disabilities.sql',
 'migration_clear_remaining_inventory.sql',
 'migration_service_division_gn_optional.sql',
 'migration_item_beneficiary_details.sql',
 'migration_remove_unwanted_seed_items.sql',
 'migration_backfill_request_eligibility.sql',
 'migration_remove_hardcoded_suppliers.sql',
 'migration_supplier_validity.sql',
 'migration_supplier_deactivation_reason.sql',
 'migration_supplier_item_validity.sql',
 'migration_supplier_product_deactivation.sql',
 'migration_supplier_agreement_history.sql',
 'migration_vision_impairment_defaults.sql',
 'migration_stock_receipt_power.sql',
 'migration_stock_receipt_power_breakdown.sql',
 'migration_stock_receipt_check_number.sql',
 'migration_unique_payment_references.sql',
];
$db=database();
foreach($files as $file){
    $path=__DIR__.'/'.$file;
    if(!is_file($path))throw new RuntimeException("Missing migration: $file");
    $db->exec(file_get_contents($path));
    echo "Applied $file\n";
    }
echo "WIDMS schema is current.\n";
