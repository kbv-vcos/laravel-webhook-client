<?php

namespace Spatie\WebhookClient;

class ISAPQueueAllocated
{
    private static string $queuePrefix = 'isap';
    private static array $validQueues = [
        'isap0',
        'isap1',
        'isap2',
        'isap3',
        'isap4',
        'isap5',
        'isap6',
        'isap7',
        'isap8',
        'isap9',
    ];

    public static function getQueueName(string $input): string
    {
        $result = 'isap0';
        $lastChar = substr($input, -1);
        $queueName = self::$queuePrefix . $lastChar;
        if (in_array($queueName, self::$validQueues)) {
            $result = $queueName;
        }
        return $result;
    }
}
