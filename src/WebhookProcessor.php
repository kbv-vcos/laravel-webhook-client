<?php

namespace Spatie\WebhookClient;

use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Bus;
use Illuminate\Foundation\Auth\User;
use MES\Core\S018JobBatch\Models\JobBatch;
use ReflectionClass;
use Spatie\WebhookClient\Events\InvalidWebhookSignatureEvent;
use Spatie\WebhookClient\Exceptions\InvalidWebhookSignature;
use Spatie\WebhookClient\Models\WebhookCall;
use Symfony\Component\HttpFoundation\Response;

class WebhookProcessor
{
    protected WebhookCall $webhookCall;

    public function __construct(
        protected Request $request,
        protected WebhookConfig $config
    ) {
    }

    public function process(): Response
    {
        $this->ensureValidSignature();

        if (! $this->config->webhookProfile->shouldProcess($this->request)) {
            return $this->createResponse();
        }

        $this->webhookCall = $webhookCall = $this->storeWebhook();

        $this->processWebhook($webhookCall);

        return $this->createResponse();
    }

    protected function ensureValidSignature(): self
    {
        if (! $this->config->signatureValidator->isValid($this->request, $this->config)) {
            event(new InvalidWebhookSignatureEvent($this->request));

            throw InvalidWebhookSignature::make();
        }

        return $this;
    }

    protected function storeWebhook(): WebhookCall
    {
        return $this->config->webhookModel::storeWebhook($this->config, $this->request);
    }

    protected function processWebhook(WebhookCall $webhookCall): void
    {
        try {
            $job = new $this->config->processWebhookJobClass($webhookCall);

            $webhookCall->clearException();

            $name = (new ReflectionClass($this))->getShortName();
            $queue = config("webhook-client.configs.{$this->config->name}.queue", 'default');
            $author = config("webhook-client.configs.{$this->config->name}.author", 'default');
            $user = User::query()->where('username', $author)->first();
            $batch = Bus::batch([$job])->name("{$name}#{$webhookCall->id}")->onQueue($queue)->dispatch();
            JobBatch::postProcess($batch->id, ['idCreatedBy' => $user->id]);
        } catch (Exception $exception) {
            $webhookCall->saveException($exception);

            throw $exception;
        }
    }

    protected function createResponse(): Response
    {
        return $this->config->webhookResponse->respondToValidWebhook($this->request, $this->config, $this->webhookCall);
    }

    public function recallWebhook(WebhookCall $webhookCall): void
    {
        $this->processWebhook($webhookCall);
    }
}
