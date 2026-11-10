<?php

return [
    'applied_title' => 'Advances applied to this bill', 'cash_separate' => 'Bill settlement includes these applications separately from cash payments. No additional money moved when the advance was applied.',
    'bill_payment_help' => 'This screen pays an existing posted bill. Future advances and recoverable salary/repair payments are recorded under Supplier Workspace → Supplier advances.',
    'help' => 'Record agreed recoverable payments here. Apply them explicitly to a posted bill for the same project/site, or record money returned by the supplier. These actions do not create VAT or change old payments.',
    'record' => 'Record a recoverable payment', 'purpose' => 'Purpose', 'advance' => 'Supplier advance',
    'salary_on_behalf' => 'Salary paid on behalf of supplier', 'repair_on_behalf' => 'Repair paid on behalf of supplier',
    'date' => 'Date', 'amount' => 'Amount (SAR)', 'project' => 'Project', 'site' => 'Site',
    'payment_account' => 'Cash / bank account', 'reference' => 'Agreement / payment reference', 'reason' => 'Reason',
    'confirm' => 'I confirm that this amount is contractually recoverable from this supplier and that the supporting agreement/payment evidence has been checked.',
    'mapping_help' => 'Finance must first configure one active Supplier Advance / Payment Recorded posting rule with a dedicated advance asset account, automatic posting enabled and approval required disabled. Posting here already requires Finance approval and payment permissions.',
    'post' => 'Post recoverable payment', 'available' => 'Available', 'apply' => 'Apply to bill', 'refund' => 'Record supplier refund',
    'bill' => 'Posted supplier bill', 'action' => 'Action', 'reverse' => 'Reverse', 'reversed' => 'Reversed',
    'empty' => 'No recoverable payments recorded.', 'saved' => 'Supplier recoverable recorded. The ledger and balance are updated.',
    'mapping_required' => 'Configure one active Supplier Advance / Payment Recorded rule with an active dedicated non-VAT asset account (not cash, bank, receivables or inventory). Enable automatic posting and disable approval required; this action itself requires approval and payment permissions.',
    'inactive_supplier' => 'This supplier is inactive.', 'invalid_date' => 'The settlement date cannot precede the original payment or bill.',
    'exceeds_available' => 'The amount exceeds the available recoverable balance.', 'exceeds_bill' => 'The amount exceeds the outstanding bill balance.',
    'invalid_bill' => 'Choose a posted open bill for the same supplier, project and site.',
    'reverse_movements_first' => 'Reverse the active applications and refunds before reversing the original payment.',
    'not_posted' => 'This recoverable is reversed or its original journal is not posted.',
    'invalid_payment' => 'Choose an active permitted cash/bank account for this supplier.',
];
