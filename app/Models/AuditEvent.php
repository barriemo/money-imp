<?php

namespace App\Models;

use Database\Factories\AuditEventFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class AuditEvent extends MoneyImpModel
{
    /** @use HasFactory<AuditEventFactory> */
    use HasFactory;
}
