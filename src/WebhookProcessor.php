<?php

namespace Spatie\WebhookClient;

use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use MES\Core\S005User\Models\User;
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
            $jobName = (new ReflectionClass($this->config->processWebhookJobClass))->getShortName();
            $queue = $this->queueAllocated($webhookCall);
            $author = config("webhook-client.configs.{$this->config->name}.author", '080383');
            $user = User::query()->where('username', $author)->first();
            $name = "{$name}-{$jobName}#{$webhookCall->id}";
            JobBatch::createBatch($user, [$job], $name, $queue);
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

    public function queueAllocated(WebhookCall $webhookCall): string
    {
        $queue = config("webhook-client.configs.{$this->config->name}.queue", 'isap0');
        if (Arr::has($webhookCall->payload, 'data.ZPP_LOIPRO04.IDOC.E1AFKOL.AUFNR')) {
            $workOrderNumber = Arr::get($webhookCall->payload, 'data.ZPP_LOIPRO04.IDOC.E1AFKOL.AUFNR');
            $queue = ISAPQueueAllocated::getQueueName($workOrderNumber);
        } else {
            $data = Arr::has($webhookCall->payload, 'data') ? Arr::get($webhookCall->payload, 'data') : [];
            $rootSelector = key($data);
            if (Arr::has($webhookCall->payload, "data.{$rootSelector}.IDOC.E1EDK01.BELNR")) {
                $po = Arr::get($webhookCall->payload, "data.{$rootSelector}.IDOC.E1EDK01.BELNR");
                $queue = ISAPQueueAllocated::getQueueName($po);
            }
        }
        return $queue;
    }
}
