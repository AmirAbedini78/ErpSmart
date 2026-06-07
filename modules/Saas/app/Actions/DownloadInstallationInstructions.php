<?php

namespace Modules\Saas\Actions;

use Carbon\Carbon;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Modules\Core\Actions\Action;
use Modules\Core\Actions\ActionFields;
use Modules\Core\Http\Requests\ActionRequest;

class DownloadInstallationInstructions extends Action
{
    /**
     * Indicates that the action does not have confirmation dialog.
     */
    public bool $withoutConfirmation = true;

    /**
     * The XHR response type that should be passed from the front-end.
     */
    public string $responseType = 'blob';

    public bool $sole = true;

    /**
     * Handle method.
     */
    public function handle(Collection $models, ActionFields $fields): Response
    {
        $tenant = $models->first();

        $name = Str::lower(Str::kebab($tenant->name));
        $documentBase = base_path();
        $phpVersion = explode('.', settings()->get('_php_version'));
        $phpVersion = $phpVersion[0] . '.' . $phpVersion[0];

        $domain = $tenant->domain;
        $subDomain = $tenant->subdomain ? $tenant->subdomain . '.' . settings()->get('domain') ?? request()->getHost(): null;
        $domain = $subDomain ?? $domain;
        $serverIp = settings()->get('_server_ip');
        $hostName = $tenant->subdomain ?? $tenant->domain;

        $content = "# Tenant onboarding instructions for John \n\n";
        $content .= "Run the Following steps to configure new tenant (John): \n\n";

        $content .= "1. Make sure you have correctly setup the domain in your DNS: \n\n";
        $content .= "Add the following A record: \n";
        $content .= "Type => A, Name => $hostName, Content=> $serverIp \n\n";

        $content .= "2. Download our provided Bash script for automating tenant setup: \n\n";
        $content .= "For Nginx:\n```bash\n wget -O $name.sh https://concordsaasmodule.com/storage/scripts/nginx.sh \n``` \n";
        $content .= "For Apache:\n```bash\n wget -O $name.sh https://concordsa asmodule.com/storage/scripts/apache2.sh \n``` \n\n";

        $content .= "3. Run \n```bash\n sudo chmod +x $name.sh \n``` \n\n";
        $content .= "4. Run \n```bash\n sudo ./$name.sh -d $domain -r $documentBase -v $phpVersion \n``` \n\n";

        $fileName = $name . '-' . Carbon::now()->toDateTimeString() . '.md';

        return response($content, 200, [
            'Content-Type' => 'text/markdown',
            'Content-Disposition' => 'attachment; filename=' . $fileName,
            'charset' => 'utf-8',
        ]);
    }

    /**
     * @param \Illuminate\Database\Eloquent\Model $model
     */
    public function authorizedToRun(ActionRequest $request, $model): bool
    {
        return $request->user()->can('view', $model);
    }

    /**
     * Action name.
     */
    public function name(): string
    {
        return __('saas::saas.download_instructions');
    }
}
