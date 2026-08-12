<?php

namespace App\Domains\Evidence;

enum EvidenceSource: string
{
    case FreeAgent = 'freeagent';
    case Bank = 'bank';
    case SupplierInvoice = 'supplier_invoice';
    case Owner = 'owner';
    case Staff = 'staff';
    case SystemInference = 'system_inference';
}
