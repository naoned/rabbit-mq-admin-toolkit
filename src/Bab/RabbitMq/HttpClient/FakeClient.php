<?php

namespace Bab\RabbitMq\HttpClient;

use Bab\RabbitMq\HttpClient;
use RuntimeException;

final class FakeClient implements HttpClient
{
    public function query(string $verb, string $uri, ?array $parameters = null): string
    {
        return "Query was stubbed";
    }
}
