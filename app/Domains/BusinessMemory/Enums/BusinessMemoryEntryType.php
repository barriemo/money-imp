<?php

namespace App\Domains\BusinessMemory\Enums;

enum BusinessMemoryEntryType: string
{
    case Note = 'note';
    case Meeting = 'meeting';
    case Email = 'email';
    case PhoneCall = 'phone_call';
    case VoiceNote = 'voice_note';
    case Document = 'document';
    case Proposal = 'proposal';
    case Contract = 'contract';
    case Decision = 'decision';
    case Promise = 'promise';
    case Question = 'question';
    case Risk = 'risk';
    case Opportunity = 'opportunity';
    case Complaint = 'complaint';
    case SupportTicket = 'support_ticket';
    case Invoice = 'invoice';
    case WorkLog = 'work_log';
    case SystemEvent = 'system_event';
}
