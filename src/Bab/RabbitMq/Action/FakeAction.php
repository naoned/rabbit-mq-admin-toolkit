<?php

namespace Bab\RabbitMq\Action;

class FakeAction extends AbstractAction
{
    public function createExchange(string $name, array $parameters): void
    {
        $this->log(sprintf('Create exchange <info>%s</info>', $name));
        $this->logParameters($parameters);
    }

    public function createQueue(string $name, array $parameters): void
    {
        $this->log(sprintf('Create queue <info>%s</info>', $name));
        $this->logParameters($parameters);
    }

    public function createBinding(string $name, string $queue, ?string $routingKey = null, array $arguments = []): void
    {
        $this->log(sprintf(
            'Create binding between exchange <info>%s</info> and queue <info>%s</info> (with routing_key: <info>%s</info>)',
            $name,
            $queue,
            $routingKey ?? 'none'
        ));

        $parameters = [
            'arguments' => $arguments,
        ];

        if(null !== $routingKey)
        {
            $parameters['routing_key'] = $routingKey;
        }

        $this->logParameters($parameters);
    }

    public function setPermissions(string $user, array $parameters = []): void
    {
        $this->log(sprintf('Grant following permissions for user <info>%s</info> on vhost <info>%s</info>: <info>%s</info>', $user, $this->vhost, json_encode($parameters)));
        $this->logParameters($parameters);
    }

    private function logParameters(array $parameters): void
    {
        $this->log(
            $this->parametersToLogFormat($parameters)
        );
    }

    private function parametersToLogFormat(array $parameters, string $name = "parameters", int $indentation = 1): string
    {
        $output = $this->indent($indentation) . "$name = [" . PHP_EOL;

        foreach($parameters as $param => $value)
        {
            if(is_array($value))
            {
                $output .= $this->parametersToLogFormat($value, $param, $indentation + 1);
            }
            else
            {
                $output .= $this->indent($indentation + 1) . "$param: $value" . PHP_EOL;
            }
        }

        $output .= $this->indent($indentation) . "]" . PHP_EOL;

        return $output;
    }

    private function indent(int $indentation): string
    {
        return str_repeat("  ", $indentation);
    }
}
