<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Invitation;
use App\Models\Tenant;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * The email with the link to join a studio. Sent right away; queued
 * notifications arrive with feat-009. It carries the plain token, which is
 * never stored.
 */
final class StaffInvitation extends Notification
{
    public function __construct(
        public readonly Invitation $invitation,
        public readonly Tenant $tenant,
        #[\SensitiveParameter] public readonly string $token,
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function url(): string
    {
        return $this->tenant->url('/join/'.$this->token);
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(sprintf('Join %s on Studio Booking', $this->tenant->name))
            ->line(sprintf('You have been invited to join %s as %s.', $this->tenant->name, strtolower($this->invitation->role->label())))
            ->action('Accept the invitation', $this->url())
            ->line(sprintf('The link expires on %s.', $this->tenant->localTime($this->invitation->expires_at)->toDayDateTimeString()));
    }
}
