<?php

namespace Spatie\WebhookClient\Tests\TestClasses;

use Illuminate\Http\Request;
use Spatie\WebhookClient\WebhookConfig;
use Spatie\WebhookClient\WebhookResponse\RespondsToWebhook;
use Symfony\Component\HttpFoundation\Response;
use Spatie\WebhookClient\Models\WebhookCall;

class CustomRespondsToWebhook implements RespondsToWebhook
{
    public function respondToValidWebhook(Request $request, WebhookConfig $config, WebhookCall $webhookCall): Response
    {
        return response()->json(['foo' => 'bar']);
    }
}
