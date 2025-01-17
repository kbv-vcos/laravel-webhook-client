<?php

namespace Spatie\WebhookClient\WebhookResponse;

use Illuminate\Http\Request;
use Spatie\WebhookClient\WebhookConfig;
use Symfony\Component\HttpFoundation\Response;
use Spatie\WebhookClient\Models\WebhookCall;

class DefaultRespondsTo implements RespondsToWebhook
{
    public function respondToValidWebhook(Request $request, WebhookConfig $config, WebhookCall $webhookCall): Response
    {
        return response()->json(['message' => 'ok']);
    }
}
