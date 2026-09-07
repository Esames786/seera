<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PurchaseOrderLine extends Model
{
    protected $fillable = [
        'purchase_order_id', 'item_id', 'description', 'quantity', 'received_quantity',
        'unit_price', 'discount_percent', 'discount_amount',
        'taxable_amount', 'vat_rate', 'vat_amount', 'total_amount',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
            'received_quantity' => 'decimal:3',
            'unit_price' => 'decimal:4',
            'discount_percent' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'taxable_amount' => 'decimal:2',
            'vat_rate' => 'decimal:2',
            'vat_amount' => 'decimal:2',
            'total_amount' => 'decimal:2',
        ];
    }

    public function outstandingQuantity(): float
    {
        return max((float) $this->quantity - (float) $this->received_quantity, 0);
    }

    /** Quantity x unit price before any discount. */
    public function grossAmount(): float
    {
        return round((float) $this->quantity * (float) $this->unit_price, 2);
    }

    /**
     * Unit price after the line discount. Goods receipts value stock at this
     * figure so a discounted order does not inflate inventory cost.
     */
    public function netUnitPrice(): float
    {
        $quantity = (float) $this->quantity;

        if ($quantity <= 0) {
            return (float) $this->unit_price;
        }

        return round((float) $this->taxable_amount / $quantity, 4);
    }

    /**
     * Work out every money column from quantity, price, discount and VAT rate.
     * Shared by the controller and the seeder so totals always agree.
     *
     * @return array{gross: float, discount_amount: float, taxable_amount: float, vat_amount: float, total_amount: float}
     */
    public static function calculate(float $quantity, float $unitPrice, float $discountPercent, float $vatRate): array
    {
        $gross = round($quantity * $unitPrice, 2);
        $discount = round($gross * $discountPercent / 100, 2);
        $taxable = round($gross - $discount, 2);
        $vat = round($taxable * $vatRate / 100, 2);

        return [
            'gross' => $gross,
            'discount_amount' => $discount,
            'taxable_amount' => $taxable,
            'vat_amount' => $vat,
            'total_amount' => round($taxable + $vat, 2),
        ];
    }

    public function purchaseOrder()
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function item()
    {
        return $this->belongsTo(Item::class);
    }
}
