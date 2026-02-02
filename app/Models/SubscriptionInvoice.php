<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SubscriptionInvoice extends Model
{
    protected $fillable = [
        'tenant_id',
        'subscription_id',
        'invoice_number',
        'invoice_date',
        'due_date',
        'subtotal_amount',
        'tax_amount',
        'tax_rate',
        'tax_name',
        'tax_type',
        'tax_inclusive',
        'tax_id',
        'amount',
        'status',
        'paid_at',
        'payment_method',
        'payment_reference',
        'notes',
    ];

    protected $casts = [
        'invoice_date' => 'datetime',
        'due_date' => 'datetime',
        'paid_at' => 'datetime',
        'subtotal_amount' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'tax_rate' => 'decimal:2',
        'amount' => 'decimal:2',
        'tax_inclusive' => 'boolean',
    ];

    /**
     * Get the tenant that owns the invoice.
     */
    public function tenant()
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }

    /**
     * Get the subscription this invoice belongs to.
     */
    public function subscription()
    {
        return $this->belongsTo(Subscription::class);
    }

    /**
     * Generate a unique invoice number.
     */
    public static function generateInvoiceNumber()
    {
        $prefix = 'INV';
        $date = date('Ymd');
        $lastInvoice = self::whereDate('created_at', today())
            ->orderBy('id', 'desc')
            ->first();
        
        $sequence = $lastInvoice ? (intval(substr($lastInvoice->invoice_number, -4)) + 1) : 1;
        
        return $prefix . '-' . $date . '-' . str_pad($sequence, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Check if invoice is overdue.
     */
    public function isOverdue()
    {
        return $this->status === 'pending' && $this->due_date < now();
    }

    /**
     * Mark invoice as paid.
     */
    public function markAsPaid($paymentMethod = null, $paymentReference = null)
    {
        $this->update([
            'status' => 'paid',
            'paid_at' => now(),
            'payment_method' => $paymentMethod,
            'payment_reference' => $paymentReference,
        ]);
    }
}
