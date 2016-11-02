<?php

namespace Konsulting\Butler\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Konsulting\Butler\SocialIdentity;

class ConfirmSocialIdentity extends Notification implements ShouldQueue
{
    use Queueable;

    protected $identity;

    /**
     * Create a new notification instance.
     *
     * @param \Konsulting\Butler\SocialIdentity $identity
     */
    public function __construct(SocialIdentity $identity)
    {
        $this->identity = $identity;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @param  mixed  $notifiable
     * @return array
     */
    public function via($notifiable)
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     *
     * @param  mixed  $notifiable
     * @return \Illuminate\Notifications\Messages\MailMessage
     */
    public function toMail($notifiable)
    {
        $provider = ucfirst($this->identity->provider);

        return (new MailMessage)
                    ->line("We received a request allow you to login with {$provider}.")
                    ->line('If you did not request this, please ignore and delete this email. The request will expire in 30 minutes.')
                    ->action('Confirm', route('butler.confirm', $this->identity->confirm_token));
    }
}
