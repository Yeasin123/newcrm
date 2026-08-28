<?php

namespace App\Livewire\Leads;

use App\Models\ConvertedLead;
use App\Models\ConvertedLeadStatus;
use App\Models\LeadDetail;
use App\Models\LeadStatus;
use App\Models\Note;
use App\Models\Reminder;
use App\Models\User;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Validate;
use Livewire\Component;

class Show extends Component
{
    public LeadDetail $lead;

    #[Validate('required|string|min:2|max:5000')]
    public string $noteBody = '';

    #[Validate('required|date')]
    public string $reminderDate = '';

    #[Validate('nullable|string|max:20')]
    public string $reminderTime = '';

    #[Validate('nullable|string|max:2000')]
    public string $reminderNote = '';

    public string $statusId = '';

    public string $assignRepId = '';

    public function mount(LeadDetail $lead): void
    {
        $this->lead = $lead;
        $this->statusId = (string) $lead->status;
        $this->assignRepId = (string) ($lead->user_id ?? '');
    }

    public function addNote(): void
    {
        $this->validateOnly('noteBody');

        Note::create([
            'lead_id' => $this->lead->id,
            'user_id' => (string) auth()->id(),
            'notes' => $this->noteBody,
            'date_time' => now()->toDateTimeString(),
            'status' => 1,
        ]);

        $this->reset('noteBody');
        $this->lead->load('leadNotes.author');
        Flux::toast(variant: 'success', text: __('Note added.'));
    }

    public function addReminder(): void
    {
        $this->validate([
            'reminderDate' => 'required|date',
            'reminderTime' => 'nullable|string|max:20',
            'reminderNote' => 'nullable|string|max:2000',
        ]);

        Reminder::create([
            'user_id' => (string) auth()->id(),
            'lead_id' => $this->lead->id,
            'date' => $this->reminderDate,
            'time' => $this->reminderTime ?: null,
            'description' => $this->reminderNote ?: null,
        ]);

        $this->reset('reminderDate', 'reminderTime', 'reminderNote');
        $this->lead->load('reminders.owner');
        Flux::toast(variant: 'success', text: __('Reminder set.'));
    }

    public function updateStatus(): void
    {
        $this->lead->status = (int) $this->statusId;
        $this->lead->save();
        Flux::toast(variant: 'success', text: __('Status updated.'));
    }

    public function assignRep(): void
    {
        $this->lead->user_id = $this->assignRepId !== '' ? (string) $this->assignRepId : null;
        $this->lead->save();
        $this->lead->load('assignedRep');
        Flux::toast(variant: 'success', text: __('Assignment updated.'));
    }

    public function sendToSaleSheet(): void
    {
        if (ConvertedLead::where('lead_id', $this->lead->id)->exists()) {
            Flux::toast(variant: 'warning', text: __('This lead is already on the Sale Sheet.'));

            return;
        }

        $snapshot = $this->lead->only([
            'campaign_id', 'user_id', 'policy_number', 'first_name', 'last_name',
            'contact', 'email', 'address', 'city', 'state', 'zip_code', 'notes',
            'status', 'reminder_id', 'reminder_status', 'property_size',
            'property_type', 'package', 'services_call_fee', 'live_charges',
            'additional_comments', 'apply_discounts', 'payment_terms',
            'instalments', 'sale_man', 'longitude', 'latitude', 'email_date_time',
            'mail_type', 'contact_text', 'phone_company',
        ]);

        $converted = ConvertedLead::create($snapshot + ['lead_id' => $this->lead->id]);

        ConvertedLeadStatus::create([
            'campaign_id' => $this->lead->campaign_id,
            'converted_lead_id' => $converted->id,
            'lead_id' => $this->lead->id,
            'status' => $this->lead->status,
            'notes' => __('Added to Sale Sheet'),
        ]);

        Flux::toast(variant: 'success', text: __('Sent to Sale Sheet.'));
    }

    public function render(): View
    {
        $this->lead->loadMissing([
            'campaign:id,name',
            'leadStatus:id,title',
            'assignedRep:id,first_name,last_name',
            'salesman:id,first_name,last_name',
            'leadNotes.author:id,first_name,last_name',
            'reminders.owner:id,first_name,last_name',
        ]);

        return view('livewire.leads.show', [
            'statuses' => LeadStatus::query()->orderBy('title')->pluck('title', 'id'),
            'reps' => User::query()->where('type', 1)->orderBy('first_name')->get(['id', 'first_name', 'last_name']),
            'onSaleSheet' => ConvertedLead::where('lead_id', $this->lead->id)->exists(),
        ])->layout('layouts.app', ['title' => trim($this->lead->first_name.' '.$this->lead->last_name) ?: 'Lead']);
    }
}
