<?php

namespace Modules\Saas\Mail;

use Illuminate\Contracts\Queue\ShouldQueue;
use Modules\Core\Common\Placeholders\PrivacyPolicyPlaceholder;
use Modules\Core\MailableTemplate\DefaultMailable;
use Modules\Core\Resource\ResourcePlaceholders;
use Modules\MailClient\Mail\MailableTemplate;
use Modules\Saas\Models\Tenant;
use Modules\Saas\Resources\Tenant as TenantResource;
use Modules\Users\Placeholders\UserPlaceholder;

class TenantSignup extends MailableTemplate implements ShouldQueue
{
    /**
     * Create a new mailable template instance.
     */
    public function __construct(protected Tenant $tenant, protected string $password)
    {
        //
    }

    /**
     * Provide the defined mailable template placeholders.
     */
    public function placeholders(): ResourcePlaceholders
    {
        return ResourcePlaceholders::make(new TenantResource, $this->tenant ?? null)
            ->push([
                PrivacyPolicyPlaceholder::make(),
                UserPlaceholder::make(fn () => $this->password, 'password')
                    ->description('Password'),
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
        return '<p>New tenant ( {{ tenant.name }} ) has been Registered.<br /></p>
                <p>Please use your email address and the password below to login:</p>
                <p>Login URL: <a href="{{ tenant.login_url }}">{{ tenant.login_url }}</a></p>
                <p>password: {{ password }}</p>';
    }

    /**
     * Provides the mail template default subject.
     */
    public static function defaultSubject(): string
    {
        return '( {{ tenant.name }} ) has been registered';
    }
}
