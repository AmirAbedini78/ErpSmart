<?php

namespace Modules\Saas\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Modules\Core\Common\Placeholders\PrivacyPolicyPlaceholder;
use Modules\Core\MailableTemplate\DefaultMailable;
use Modules\Core\MailableTemplate\MailableTemplate;
use Modules\Core\Resource\ResourcePlaceholders;
use Modules\Saas\Models\Package;
use Modules\Saas\Models\Tenant;
use Modules\Saas\Resources\Tenant as TenantResource;
use Modules\Saas\Resources\Package as PackageResource;

class TenantOverdue extends MailableTemplate
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     */
    public function __construct(protected Tenant $tenant, protected Package $package)
    {}

    /**
     * Provide the defined mailable template placeholders.
     */
    public function placeholders(): ResourcePlaceholders
    {
        return ResourcePlaceholders::make(new TenantResource, $this->tenant ?? null)
            ->push([
                ResourcePlaceholders::make(new PackageResource, $this->package ?? null),
                PrivacyPolicyPlaceholder::make(),
            ]);
    }

    /**
     * Provides the mailable template default configuration.
     */
    public static function default(): DefaultMailable
    {
        return new DefaultMailable(static::defaultHtmlTemplate(), static::defaultSubject());
    }

    /**
     * Provides the mail template default message.
     */
    public static function defaultHtmlTemplate(): string
    {
        return '<p>Your account {{  tenant.name }} expired on {{ tenant.expiry_date }} is overdue.<br /></p>
                <p> Please clear your dues and renew your subscription to avoid service interruption. </p>
                <p>Thank you</p>';
    }

    /**
     * Provides the mail template default subject.
     */
    public static function defaultSubject(): string
    {
        return '( {{ tenant.name }} ) is overdue';
    }
}
