<?php

namespace Modules\Saas\Notifications;

use Illuminate\Contracts\Queue\ShouldQueue;
use Modules\Core\Notification;
use Modules\Saas\Mail\TenantSignup;
use Modules\Saas\Models\Tenant;

class TenantSignupNotification extends Notification implements ShouldQueue
{
    /**
     * Create a new notification instance.
     */
    public function __construct(protected Tenant $tenant, protected string $password)
    {
        //
    }

    public function toMail(object $notifiable)
    {
        return (new TenantSignup($this->tenant, $this->password))->to($notifiable);
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
