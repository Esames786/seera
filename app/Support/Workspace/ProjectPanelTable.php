<?php

namespace App\Support\Workspace;

/** Plain text cells and authorized links; templates escape every value. */
class ProjectPanelTable
{
    public static function columns(string $panel): array
    {
        return match ($panel) {
            'customer' => ['Code', 'Customer', 'VAT / CR', 'Contact'],
            'sites' => ['Code', 'Site', 'Supervisor', 'Address', 'Status', 'Geofence / radius', 'Inside only / offline allowed'],
            'staff' => ['Code', 'Employee', 'Department', 'Designation', 'Site', 'Manager', 'Status', 'Mobile access'],
            'warehouses' => ['Code', 'Warehouse', 'Site', 'Incharge', 'Valuation', 'Status', 'Stocked items', 'On hand (by unit)', 'Stock value (SAR)'],
            'stock' => ['Warehouse', 'Item', 'Unit', 'On hand', 'Average cost', 'Current value (SAR)'],
            'suppliers' => ['Code', 'Supplier', 'Category', 'Contact', 'Status', 'Project POs (not cancelled)', 'Purchase value (SAR)'],
            'requests' => ['Request', 'Date', 'Required date', 'Status', 'Linked project orders'],
            'orders' => ['Order', 'Supplier', 'Date', 'Total (SAR)', 'Status / receiving', 'Billing state (visible receipts)'],
            'receipts' => ['Receipt', 'PO', 'Supplier', 'Warehouse', 'Date', 'Status', 'Stock updated', 'Invoicing state'],
            'materials' => ['Issue', 'Date', 'Site', 'Warehouse', 'Item', 'Unit', 'Issued quantity', 'Material cost (SAR)', 'Status'],
            'invoices' => ['Invoice', 'Date', 'Customer', 'Taxable', 'VAT', 'Total', 'Received', 'Outstanding', 'Status', 'Local ZATCA'],
            'payments' => ['Invoice', 'Date', 'Amount (SAR)', 'Method', 'Reference'],
            'activity' => ['Date', 'User', 'Action', 'Reference', 'Status'],
            default => [],
        };
    }

    private static function invoiceState(float $accepted, float $invoiced): string
    {
        return $accepted <= 0 ? 'Nothing to invoice yet' : ($invoiced + 0.0005 >= $accepted ? 'Fully invoiced' : ($invoiced > 0 ? 'Partly invoiced' : 'Received but not invoiced'));
    }

    public static function row($row, array $data): array
    {
        ['panel' => $panel, 'project' => $project, 'user' => $user, 'returnTo' => $returnTo] = $data;
        $money = fn ($v) => number_format((float) $v, 2);
        $date = fn ($v) => $v?->format('Y-m-d') ?? '-';
        $yes = fn ($v) => $v ? 'Yes' : 'No';
        $purchase = $data['supplierPurchases'][$row->id] ?? null;
        $receipt = $data['orderReceipts'][$row->id] ?? null;
        $stock = $user->hasPermission('Warehouse Stock', 'view');
        $po = $user->hasPermission('Purchase Orders', 'view');
        $cells = match ($panel) {
            'customer' => [$row->code, $row->name, ($row->vat_number ?? '-').' / '.($row->cr_number ?? '-'), implode(' / ', array_filter([$row->contact_person, $row->phone, $row->email]))],
            'sites' => [$row->code, $row->name, $row->supervisor?->name, $row->address, $row->status, $yes($row->geofence_enabled).' / '.$row->geofence_radius.' m', $yes($row->attendance_inside_only).' / '.$yes($row->offline_attendance_allowed)],
            'staff' => [$row->employee_code, $row->name, $row->department?->name, $row->designation?->name, $row->site?->name, $row->manager?->name, $row->status, $yes($row->mobile_access)],
            'warehouses' => [$row->code, $row->name, $row->site?->name, $row->incharge?->name, $row->valuation_method, $row->status, $stock ? $row->stocked_items : 'Restricted', $stock ? ($data['warehouseQuantities'][$row->id] ?? 'No positive stock') : 'Restricted', $stock ? $money($row->stocks_sum_total_value) : 'Restricted'],
            'stock' => [$row->warehouse?->name, $row->item?->name, $row->item?->unit?->name, $row->quantity, $row->average_cost, $money($row->total_value)],
            'suppliers' => [$row->code, $row->name, $row->category, implode(' / ', array_filter([$row->contact_person, $row->phone, $row->email])), $row->status, $po ? ($purchase?->orders ?? 0) : 'Restricted', $po ? $money($purchase?->value) : 'Restricted'],
            'requests' => [$row->pr_number, $date($row->request_date), $date($row->required_date), $row->status, $po ? $row->purchase_orders_count : 'Restricted'],
            'orders' => [$row->po_number, $row->supplier?->name, $date($row->po_date), $money($row->total_amount), $row->status, $user->hasPermission('Goods Receipts', 'view') ? self::invoiceState((float) $receipt?->accepted, (float) $receipt?->invoiced) : 'Restricted'],
            'receipts' => [$row->grn_number, $row->purchaseOrder?->po_number, $row->supplier?->name, $row->warehouse?->name, $date($row->received_date), $row->status, $yes($row->stock_updated), $row->status === 'posted' ? self::invoiceState((float) $row->lines_sum_accepted_quantity, (float) $row->lines_sum_invoiced_quantity) : 'Not posted'],
            'materials' => [$row->reference_number, $date($row->movement_date), $row->site?->name, $row->warehouse?->name, $row->item?->name, $row->item?->unit?->name, $row->out_quantity, $money($row->value), 'posted'],
            'invoices' => [$row->invoice_number, $date($row->invoice_date), $row->customer?->name, $money($row->taxable_amount), $money($row->vat_amount), $money($row->total_amount), $money($row->received_amount), $money($row->balance_amount), $row->payment_status, $row->zatca_status],
            'payments' => [$row->invoice?->invoice_number, $date($row->receipt_date), $money($row->amount), $row->payment_method, $row->reference_number],
            'activity' => [$row->created_at?->format('Y-m-d H:i'), $row->user_name, $row->action, $row->description, $row->status],
        };
        $actions = [];
        $link = function ($label, $module, $action, $route, $params) use (&$actions, $user, $returnTo) {
            if ($user->hasPermission($module, $action)) {
                $actions[] = ['label' => $label, 'url' => route($route, [...(array) $params, 'return_to' => $returnTo])];
            }
        };
        $entity = match ($panel) {
            'customer' => ['Customers', 'admin.master.customers'], 'staff' => ['HR', 'admin.hr.employees'],
            'suppliers' => ['Suppliers', 'admin.master.suppliers'], 'warehouses' => ['Warehouses', 'admin.master.warehouses'],
            'requests' => ['Purchase Requests', 'admin.inventory.purchase-requests'], 'orders' => ['Purchase Orders', 'admin.inventory.purchase-orders'],
            'receipts' => ['Goods Receipts', 'admin.inventory.goods-receipts'], 'invoices' => ['Accounts Receivable', 'admin.accounting.accounts-receivable'],
            default => null,
        };
        if ($entity) {
            $link('View', $entity[0], 'view', $entity[1].'.show', [$row->id]);
            if (in_array($panel, ['customer', 'staff', 'suppliers'])) {
                $link('Edit / Manage', $entity[0], 'edit', $entity[1].'.edit', [$row->id]);
            }
        }
        if ($panel === 'sites') {
            $link('View Site', 'Sites', 'view', 'admin.master.sites.show', [$row->id]);
            $link('Edit Site', 'Sites', 'edit', 'admin.master.sites.project.edit', [$project->id, $row->id]);
        }
        if ($panel === 'warehouses') {
            $link('Stock On Hand', 'Warehouse Stock', 'view', 'admin.inventory.stock.index', ['warehouse' => $row->id]);
            $link('Stock Ledger', 'Stock Ledger', 'view', 'admin.inventory.stock-ledger', ['warehouse' => $row->id]);
        }
        if ($panel === 'materials') {
            $link('View Issue', 'Stock Issues', 'view', 'admin.inventory.stock-issues.show', [$row->reference_id]);
        }
        if ($panel === 'payments' && $row->invoice) {
            $link('View Invoice', 'Accounts Receivable', 'view', 'admin.accounting.accounts-receivable.show', [$row->invoice->id]);
        }
        if ($panel === 'invoices' && in_array($row->payment_status, ['unpaid', 'partially_paid'])) {
            $link('Record Receipt', 'Accounts Receivable', 'process', 'admin.accounting.accounts-receivable.receipt', [$row->id]);
        }

        return compact('cells', 'actions');
    }
}
