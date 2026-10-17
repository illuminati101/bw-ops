<?php

namespace App\Services\Messaging;

use App\Models\OutboundMessage;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/** Low-level senders. Everything sent is recorded in outbound_messages. */
class Channels
{
    public function sms(string $to, string $body, ?User $user = null, bool $sensitive = false): OutboundMessage
    {
        $driver = config('bwops.integrations.sms_driver') === 'twilio' && $this->twilioReady() ? 'twilio' : 'log';
        $msg = OutboundMessage::create([
            'channel' => 'sms', 'to' => $to, 'user_id' => $user?->id, 'body' => $body, 'driver' => $driver,
            'status' => 'queued', 'sensitive' => $sensitive,
        ]);

        if ($driver === 'log') {
            $msg->update(['status' => 'logged']);
            Log::info("[SMS to {$to}] {$body}");

            return $msg;
        }

        try {
            $cfg = config('bwops.integrations.twilio');
            $res = Http::asForm()->withBasicAuth($cfg['sid'], $cfg['token'])->timeout(10)
                ->post("https://api.twilio.com/2010-04-01/Accounts/{$cfg['sid']}/Messages.json", [
                    'To' => $to, 'From' => $cfg['from'], 'Body' => $body,
                ]);
            $res->successful()
                ? $msg->update(['status' => 'sent', 'provider_id' => $res->json('sid')])
                : $msg->update(['status' => 'failed', 'error' => $res->body()]);
        } catch (\Throwable $e) {
            $msg->update(['status' => 'failed', 'error' => $e->getMessage()]);
        }

        return $msg;
    }

    /** Automated voice call that reads a message (used for on-call escalation). */
    public function voice(string $to, string $say, ?User $user = null): OutboundMessage
    {
        $ready = config('bwops.integrations.sms_driver') === 'twilio' && $this->twilioReady();
        $msg = OutboundMessage::create([
            'channel' => 'voice', 'to' => $to, 'user_id' => $user?->id, 'body' => $say, 'driver' => $ready ? 'twilio' : 'log', 'status' => 'queued',
        ]);
        if (! $ready) {
            $msg->update(['status' => 'logged']);

            return $msg;
        }
        try {
            $cfg = config('bwops.integrations.twilio');
            $twiml = '<Response><Say voice="Polly.Amy" loop="3">'.e($say).'</Say></Response>';
            $res = Http::asForm()->withBasicAuth($cfg['sid'], $cfg['token'])->timeout(10)
                ->post("https://api.twilio.com/2010-04-01/Accounts/{$cfg['sid']}/Calls.json", ['To' => $to, 'From' => $cfg['from'], 'Twiml' => $twiml]);
            $res->successful()
                ? $msg->update(['status' => 'sent', 'provider_id' => $res->json('sid')])
                : $msg->update(['status' => 'failed', 'error' => $res->body()]);
        } catch (\Throwable $e) {
            $msg->update(['status' => 'failed', 'error' => $e->getMessage()]);
        }

        return $msg;
    }

    /**
     * Masked call: Twilio rings $from first, then connects them to $to.
     * Both parties only ever see the business number.
     */
    public function bridgeCall(string $from, string $to, ?User $user = null): OutboundMessage
    {
        $ready = $this->twilioReady();
        $msg = OutboundMessage::create([
            'channel' => 'voice', 'to' => $from, 'user_id' => $user?->id, 'body' => 'Masked call bridge', 'driver' => $ready ? 'twilio' : 'log', 'status' => 'queued', 'sensitive' => true,
        ]);
        if (! $ready) {
            $msg->update(['status' => 'logged', 'error' => 'Twilio not configured']);

            return $msg;
        }
        $cfg = config('bwops.integrations.twilio');
        $twiml = '<Response><Say voice="Polly.Amy">Connecting you now.</Say><Dial callerId="'.e($cfg['from']).'" timeout="30">'.e($to).'</Dial></Response>';
        $res = Http::asForm()->withBasicAuth($cfg['sid'], $cfg['token'])->timeout(10)
            ->post("https://api.twilio.com/2010-04-01/Accounts/{$cfg['sid']}/Calls.json", ['To' => $from, 'From' => $cfg['from'], 'Twiml' => $twiml]);
        $res->successful() ? $msg->update(['status' => 'sent', 'provider_id' => $res->json('sid')]) : $msg->update(['status' => 'failed', 'error' => $res->body()]);

        return $msg;
    }

    public function email(User|string $to, BrandedMail $mail): ?OutboundMessage
    {
        $address = $to instanceof User ? $to->email : $to;
        if (! $address) {
            return null;
        }
        $msg = OutboundMessage::create([
            'channel' => 'email', 'to' => $address, 'user_id' => $to instanceof User ? $to->id : null,
            'subject' => $mail->subjectLine, 'body' => implode("\n", $mail->lines), 'driver' => config('mail.default'), 'status' => 'queued',
        ]);
        try {
            Mail::to($address)->send($mail);
            $msg->update(['status' => config('mail.default') === 'log' ? 'logged' : 'sent']);
        } catch (\Throwable $e) {
            $msg->update(['status' => 'failed', 'error' => $e->getMessage()]);
        }

        return $msg;
    }

    public function push(User $user, string $title, string $body, array $data = [], ?string $app = null): void
    {
        $tokens = $user->deviceTokens()->when($app, fn ($q) => $q->where('app', $app))->pluck('token')->all();
        if (! $tokens) {
            return;
        }
        $msg = OutboundMessage::create([
            'channel' => 'push', 'to' => count($tokens).' device(s)', 'user_id' => $user->id, 'subject' => $title, 'body' => $body, 'driver' => 'expo', 'status' => 'queued',
        ]);
        try {
            $payload = array_map(fn ($t) => [
                'to' => $t, 'title' => $title, 'body' => $body, 'data' => $data, 'sound' => 'default', 'priority' => 'high',
                'channelId' => $data['channel'] ?? 'default',
            ], $tokens);
            $req = Http::timeout(10)->acceptJson();
            if ($token = config('bwops.integrations.expo_access_token')) {
                $req = $req->withToken($token);
            }
            $res = $req->post('https://exp.host/--/api/v2/push/send', $payload);
            $msg->update(['status' => $res->successful() ? 'sent' : 'failed', 'error' => $res->successful() ? null : $res->body()]);
        } catch (\Throwable $e) {
            $msg->update(['status' => 'failed', 'error' => $e->getMessage()]);
        }
    }

    public function twilioReady(): bool
    {
        $cfg = config('bwops.integrations.twilio');

        return filled($cfg['sid']) && filled($cfg['token']) && filled($cfg['from']);
    }
}
