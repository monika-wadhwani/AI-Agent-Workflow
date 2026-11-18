<?php

namespace App\Ai\Tools;

use App\Contracts\Tool;
use App\Mail\AgentEmail;
use Illuminate\Support\Facades\Mail;
use InvalidArgumentException;

class SendEmailTool implements Tool
{
    public function name(): string
    {
        return 'send_email';
    }

    public function description(): string
    {
        return 'Send an email. A human must approve every email before it is sent.';
    }

    public function parameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'to' => ['type' => 'string', 'description' => 'Recipient email address'],
                'subject' => ['type' => 'string'],
                'body' => ['type' => 'string'],
            ],
            'required' => ['to', 'subject', 'body'],
        ];
    }

    public function requiresApproval(): bool
    {
        return true;
    }

    public function execute(array $arguments): string
    {
        $to = (string) ($arguments['to'] ?? '');

        if (filter_var($to, FILTER_VALIDATE_EMAIL) === false) {
            throw new InvalidArgumentException("[{$to}] is not a valid email address.");
        }

        Mail::to($to)->send(new AgentEmail(
            (string) ($arguments['subject'] ?? ''),
            (string) ($arguments['body'] ?? ''),
        ));

        return "Email sent to {$to}.";
    }
}