<?php

namespace Bab\RabbitMq;

use ArrayAccess;

interface Configuration extends ArrayAccess
{
    public function vhost(): string;

    public function hasDeadLetterExchange(): bool;
    public function hasUnroutableExchange(): bool;

    public function hasQueueTypeBeenDefined(): bool;
    public function queueType(): string;
}
