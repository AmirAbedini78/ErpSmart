<?php

namespace Modules\Saas\Notifications;

use Illuminate\Contracts\Queue\ShouldQueue;
use Modules\Core\Notification;
use Modules\Saas\Mail\TenantDomainIntegration;
use Modules\Saas\Models\Tenant;

class TenantDomainIntegrationNotification extends Notification implements ShouldQueue
{
    /**
     * Create a new notification instance.
     */
    public function __construct(protected Tenant $tenant)
    {
        //
    }

    /**
     * Get the mail representation of the notification.
     *
     * @return \Illuminate\Notifications\Messages\MailMessage|\Modules\Core\MailableTemplate\MailableTemplate
     */
    public function toMail(object $notifiable): TenantDomainIntegration
    {
        return (new TenantDomainIntegration($this->tenant))->to($notifiable);
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray($notifiable): array
    {
        return [
            //
        ];
    }
}
