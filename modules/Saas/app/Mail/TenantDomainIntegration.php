<?php

namespace Modules\Saas\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\SerializesModels;
use Modules\Core\Common\Placeholders\GenericPlaceholder;
use Modules\Core\Common\Placeholders\PrivacyPolicyPlaceholder;
use Modules\Core\MailableTemplate\DefaultMailable;
use Modules\Core\Resource\ResourcePlaceholders;
use Modules\MailClient\Mail\MailableTemplate;
use Modules\Saas\Models\Tenant;
use Modules\Saas\Resources\Tenant as TenantResource;

class TenantDomainIntegration extends MailableTemplate implements ShouldQueue
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     */
    public function __construct(protected Tenant $tenant)
    {}

    /**
     * Provide the defined mailable template placeholders.
     */
    public function placeholders(): ResourcePlaceholders
    {
        return ResourcePlaceholders::make(new TenantResource, $this->tenant ?? null)
            ->push([
                GenericPlaceholder::make(
                    'server_ip',
                    settings()->get('_server_ip'),
                ),
                GenericPlaceholder::make(
                    'company_name',
                    settings()->get('company_name'),
                ),
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
        return '<p>Dear {{ tenant.name }},</p>
        <p>Thank you for registering with {{ company_name }}. Your account has been successfully created, and you can now set up your custom domain.</p>
        <h2>Domain Setup Instructions</h2>
        <p>Please configure your domain\'s DNS settings with the following details:</p>
        <ul>
            <li><strong>A Record:</strong> Point your domain to <code>{{ server_ip }}</code></li>
            <li><strong>CNAME Record:</strong> If applicable, set <code>www</code> to <code>{{ tenant.domain }}</code></li>
        </ul>
        <p>Once you\'ve updated your DNS settings, it may take some time to propagate. You can verify the setup using tools like <a href="https://dnschecker.org/">DNS Checker</a>.</p>';
    }

    /**
     * Provides the mail template default subject.
     */
    public static function defaultSubject(): string
    {
        return 'Domain Setup Instructions';
    }
}
