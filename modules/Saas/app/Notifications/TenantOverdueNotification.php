<?php

namespace Modules\Saas\Notifications;

use Illuminate\Contracts\Queue\ShouldQueue;
use Modules\Core\MailableTemplate\MailableTemplate;
use Modules\Core\Notification;
use Modules\Saas\Mail\TenantOverdue;
use Modules\Saas\Models\Package;
use Modules\Saas\Models\Tenant;

class TenantOverdueNotification extends Notification implements ShouldQueue
{
    /**
     * Create a new notification instance.
     */
    public function __construct(protected Tenant $tenant, protected Package $package)
    {}

    /**
     * Get the mail representation of the notification.
     *
     */
    public function toMail(object $notifiable): TenantOverdue&MailableTemplate
    {
        return (new TenantOverdue($this->tenant, $this->package))->to($notifiable);
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
