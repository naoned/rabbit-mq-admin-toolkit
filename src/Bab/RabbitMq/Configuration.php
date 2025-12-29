<?php

namespace Bab\RabbitMq;

use ArrayAccess;

interface Configuration extends ArrayAccess
{
    public function getVhost(): string;

    public function hasDeadLetterExchange(): bool;

    public function hasUnroutableExchange(): bool;
}
