<?php

namespace App\Http\Controllers\Webhooks;

use App\Models\ConstantContact;
use App\Models\LeadDetail;
use App\Models\Webhook;
use App\Services\Lead\EmailWebhookLeadService;
use App\Services\Lead\EntranceReplyService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Inbound email-platform lead webhooks. Ported from the legacy QuoteController,
 * CEWebhookController, CamWebhookController and NewEmailWebhook. Paths and
 * response bodies are byte-identical.
 */
class EmailWebhookController
{
    public function __construct(
        private readonly EmailWebhookLeadService $emailLeads,
        private readonly EntranceReplyService $entrance,
    ) {}

    /** /quote/email/webhook, /pquote/email/webhook, /cel/webhook — channel via route default. */
    public function engagement(Request $request, string $channel): string
    {
        $config = config("email_webhooks.channels.$channel");

        abort_if(! is_array($config), 404);

        $this->emailLeads->handle($request->all(), $config);

        return 'ok';
    }

    /** /campaign/webhook — a straight lead create, JSON response. */
    public function campaign(Request $request)
    {
        $phone = $request->input('phone', $request->input('contact'));

        if (is_string($phone)) {
            $phone = (string) preg_replace('/^\+?1[\s\-]*/', '', trim($phone));
        }

        $lead = LeadDetail::query()->create([
            'campaign_id' => 162,
            'first_name' => $request->input('first_name'),
            'last_name' => $request->input('last_name'),
            'email' => $request->input('email'),
            'contact' => $phone,
        ]);

        return response()->json(['status' => 'success', 'lead_id' => $lead->id]);
    }

    /** /change/email/webhook — update a known lead's email. */
    public function changeEmail(Request $request)
    {
        $contact = $request->input('Contact', $request->input('contact'));
        $email = $request->input('Email', $request->input('email'));

        if (! $contact || ! $email) {
            Log::warning('Change-email webhook missing Contact or Email', $request->all());

            return response('missing data', 400);
        }

        if (strlen((string) $contact) === 11 && str_starts_with((string) $contact, '1')) {
            $contact = substr((string) $contact, 1);
        }

        LeadDetail::query()->where('contact', $contact)->first()?->update([
            'email' => $email,
            'lead_tag' => 'Inquiry',
        ]);

        return 'ok';
    }

    /** /new/email/webhook — Instantly "email/link clicked" engagement string. */
    public function linkClick(Request $request): string
    {
        $output = data_get($request->all(), 'output.Item 1');

        if (! is_string($output)) {
            return 'ok';
        }

        $pattern = null;

        if (Str::contains($output, 'email_link_clicked')) {
            $pattern = '/^(.+?)email_link_clicked(\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\.\d{3}Z)(.*)$/';
        } elseif (Str::contains($output, 'link_clicked')) {
            $pattern = '/^(.+?)link_clicked(\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\.\d{3}Z)(.*)$/';
        }

        if ($pattern === null || ! preg_match($pattern, $output, $m)) {
            return 'ok';
        }

        $this->storeLinkClick(
            email: $m[1],
            dateAndTime: Carbon::parse($m[2])->format('m/d/Y H:i'),
            campaign: $m[3],
        );

        return 'ok';
    }

    /** /second/new/webhook — an SMS reply nested under `data`, flagged LG. */
    public function secondNew(Request $request): string
    {
        $data = (array) $request->input('data', []);

        $this->entrance->handle($data, config('entrances.channels.lg'));

        return 'ok';
    }

    protected function storeLinkClick(string $email, string $dateAndTime, string $campaign): void
    {
        $lead = LeadDetail::query()->where('email', $email)->first();

        if (! $lead) {
            return;
        }

        $cc = ConstantContact::query()->create([
            'lead_id' => $lead->id,
            'email_text' => 'Clicked Email',
            'campaign' => $campaign,
            'email_time' => $dateAndTime,
            'webhook_name' => 'Inst',
        ]);

        $lead = LeadDetail::query()->find($cc->lead_id);

        if (! $lead) {
            return;
        }

        $update = [
            'mail' => 1,
            'message' => $cc->email_text,
            'priority_indx' => 1,
            'show_status' => 1,
            'priority_checker' => null,
            'not_pull' => null,
            'msg_date' => $cc->created_at,
        ];

        if (empty($lead->user_id)) {
            $update['user_id'] = null;
        }

        $lead->update($update);

        $exists = Webhook::query()
            ->where('lead_id', $lead->id)
            ->whereDate('STORE_DATE', Carbon::now()->startOfDay())
            ->where('INST', 'INST')
            ->exists();

        if (! $exists) {
            Webhook::create([
                'lead_id' => $lead->id,
                'STORE_DATE' => Carbon::now(),
                'INST' => 'INST',
            ]);
        }
    }
}
