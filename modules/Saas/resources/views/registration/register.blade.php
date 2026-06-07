<x-saas::layouts.auth>
    @section('title', __('saas::saas.register'))
    @section('subtitle', __('saas::saas.register_subheading'))

    <the-float-notifications></the-float-notifications>

    <tenant-register
        :domain-enabled="{{ json_encode($enableDomainSignup) }}"
        :subdomain-enabled="{{ json_encode($enableSubdomainSignup) }}"
        :countries="{{ $countries }}"
        :packages="{{ $packages }}"
    ></tenant-register>
</x-saas::layouts.auth>
