<?php

declare(strict_types=1);

namespace App\Notifications;

use InvalidArgumentException;

// ----------------------------------------------------------------------------
// CONTRACT
// All senders implement the same interface.
// The caller never needs to know which implementation it received.
//
// $recipient is channel-specific: an email address, a phone number, or a
// Slack channel ID. $message is the notification content. Subject lines,
// formatting, and provider-specific payload construction are handled inside
// each implementation, not at this interface level.
// ----------------------------------------------------------------------------

interface NotificationSenderInterface
{
    public function send(string $recipient, string $message): void;
}

// ----------------------------------------------------------------------------
// IMPLEMENTATIONS
// Transport details are encapsulated per sender.
// Actual HTTP clients or SDK calls would be injected via the constructor.
// ----------------------------------------------------------------------------

final class EmailNotificationSender implements NotificationSenderInterface
{
    public function __construct(private readonly array $config) {}

    public function send(string $recipient, string $message): void
    {
        // In a real implementation: inject a mailer client (e.g. Laravel Mailer,
        // SES SDK) and dispatch the message. Omitted here to keep the focus on
        // the factory pattern, not on transport specifics.
    }
}

final class SmsNotificationSender implements NotificationSenderInterface
{
    public function __construct(private readonly array $config) {}

    public function send(string $recipient, string $message): void
    {
        // $recipient is a phone number (E.164 format)
        // In a real implementation: inject a Twilio or Vonage client.
    }
}

final class SlackNotificationSender implements NotificationSenderInterface
{
    public function __construct(private readonly array $config) {}

    public function send(string $recipient, string $message): void
    {
        // $recipient is a Slack channel ID (e.g. '#alerts') or webhook URL
        // In a real implementation: POST to the Slack Incoming Webhooks API.
    }
}

// ----------------------------------------------------------------------------
// FACTORY
// Centralises the decision of which implementation to instantiate.
// Throws clearly on an unrecognised channel instead of returning null.
// ----------------------------------------------------------------------------

final class NotificationSenderFactory
{
    public function __construct(private readonly array $config) {}

    public function make(string $channel): NotificationSenderInterface
    {
        return match ($channel) {
            'email' => new EmailNotificationSender($this->config['email'] ?? []),
            'sms'   => new SmsNotificationSender($this->config['sms'] ?? []),
            'slack' => new SlackNotificationSender($this->config['slack'] ?? []),
            default => throw new InvalidArgumentException(
                "Unsupported notification channel: '{$channel}'. " .
                "Supported: email, sms, slack."
            ),
        };
    }
}

// ----------------------------------------------------------------------------
// SERVICE — depends on the interface, not on any concrete sender
// ----------------------------------------------------------------------------

final class NotificationService
{
    public function __construct(
        private readonly NotificationSenderFactory $factory,
    ) {}

    public function notify(string $channel, string $recipient, string $message): void
    {
        $sender = $this->factory->make($channel);
        $sender->send($recipient, $message);
    }
}

// ----------------------------------------------------------------------------
// USAGE
// ----------------------------------------------------------------------------

$config = [
    'email' => ['from' => 'no-reply@example.com'],
    'sms'   => ['from' => '+15550001234'],
    'slack' => ['webhook_url' => 'https://hooks.slack.com/...'],
];

$factory = new NotificationSenderFactory($config);
$service = new NotificationService($factory);

$service->notify('email', 'user@example.com', 'Your invoice is available.');
$service->notify('slack', '#alerts',           'Build #512 failed.');

// ----------------------------------------------------------------------------
// LARAVEL CONTAINER APPROACH
// In a Laravel application, an alternative to an explicit factory is to
// register each sender in the container and resolve by key.
// This avoids an explicit factory class when the container's capabilities suffice.
//
// AppServiceProvider::register():
//
// $this->app->bind('notification.email', fn () =>
//     new EmailNotificationSender(config('notifications.email'))
// );
//
// $this->app->bind('notification.sms', fn () =>
//     new SmsNotificationSender(config('notifications.sms'))
// );
//
// Resolution:
// $sender = app("notification.{$channel}");
// ----------------------------------------------------------------------------
