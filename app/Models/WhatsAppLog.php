<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One WhatsApp message attempt and Meta's answer.
 */
class WhatsAppLog extends Model
{
    protected $table = 'whatsapp_logs';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'purpose',
        'business_date',
        'recipient',
        'status',
        'message_id',
        'error',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['business_date' => 'date'];
    }

    public function sent(): bool
    {
        return $this->status === 'sent';
    }
}
