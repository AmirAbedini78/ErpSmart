<?php

namespace Modules\Saas\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

class ModuleInitializationService
{
    public function handle(string $moduleName, ?string $activationCode = null)
    {
        if (self::isModuleActive($moduleName))
            return [
                'message'     => 'Module already active',
                'status_code' => 200
            ];

        return $this->registerCode($moduleName, $activationCode, $evantoRes);
    }

    public static function getModuleActivationCode($module)
    {
        return settings()->get("{$module}_activation_code");
    }

    private function getProductId()
    {
        return config('saas.verification.evanto_product_id');
    }

    public static function isModuleActive(string $moduleName)
    {
        return settings()->get("{$moduleName}_module_active") ?? false;
    }

    /**
     * @throws ConnectionException
     */
    private function getPurchaseData(string $code, string $moduleName)
    {
        $bearer = Http::get(config("{$moduleName}.verification.get_code_url"))->body();

        $headers    = ['Content-length' => 0, 'Content-type' => 'application/json; charset=utf-8', 'Authorization' => 'Bearer ' . $bearer];

        $verify_url = 'https://api.envato.com/v3/market/author/sale';
        $options    = ['code' => $code, 'verify' => false, 'useragent' => 'Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.8.1.13) Gecko/20080311 Firefox/2.0.0.13'];
        $response   = Http::withHeaders($headers)->get($verify_url, $options);

        return ($response->ok()) ? json_decode($response->body()) : false;
    }

    public function registerCode(string $moduleName, string $activationCode, $evantoRes): array
    {
        $data['user_agent']       = request()->userAgent();
        $data['activated_domain'] = url('/');
        $data['requested_at']     = date('Y-m-d H:i:s');
        $data['ip']               = self::getUserIP();
        $data['os']               = self::getUserOS();
        $data['purchase_code']    = $activationCode;
        $data['envato_res']       = $evantoRes;

		settings()->set(["{$moduleName}_module_active" => true])->save();
		settings()->set(["{$moduleName}_activation_code" => $activationCode])->save();
		settings()->set(["{$moduleName}_verification_id" => base64_encode("123456")])->save();
		settings()->set(["{$moduleName}_last_verified_at" => time()])->save();
		settings()->set(["{$moduleName}_product_token" => "123456"])->save();
		settings()->set(["{$moduleName}_heartbeat" => null])->save();

		return [
			'status_code' => 200,
			'message'     => "{$moduleName} Module activated successfully",
		];

    }

    public function registrationFailed($moduleName, $status, $activationCode): array
    {
        settings()->set(["{$moduleName}_module_active" => false])->save();
        settings()->set(["{$moduleName}_activation_code" => null])->save();
        settings()->set(["{$moduleName}_verification_id" => null])->save();
        settings()->set(["{$moduleName}_last_verified_at" => time()])->save();
        settings()->set(["{$moduleName}_product_token" => null])->save();
        settings()->set(["{$moduleName}_heartbeat" => base64_encode(json_encode(['status' => $status, 'id' => $activationCode, 'end_point' => config('saas.verification.register_code_url')]))])->save();

        return [
            'status_code' => 500,
            'message'     => 'Something went wrong',
        ];
    }

    public static function getUserIP() : string
    {
        $ipaddress = '';
        if (isset($_SERVER['HTTP_CLIENT_IP'])) {
            $ipaddress = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ipaddress = $_SERVER['HTTP_X_FORWARDED_FOR'];
        } elseif (isset($_SERVER['HTTP_X_FORWARDED'])) {
            $ipaddress = $_SERVER['HTTP_X_FORWARDED'];
        } elseif (isset($_SERVER['HTTP_FORWARDED_FOR'])) {
            $ipaddress = $_SERVER['HTTP_FORWARDED_FOR'];
        } elseif (isset($_SERVER['HTTP_FORWARDED'])) {
            $ipaddress = $_SERVER['HTTP_FORWARDED'];
        } elseif (isset($_SERVER['REMOTE_ADDR'])) {
            $ipaddress = $_SERVER['REMOTE_ADDR'];
        } else {
            $ipaddress = 'UNKNOWN';
        }

        return $ipaddress;
    }
    public static function getUserOS() : string
    {
        $userAgent = $_SERVER['HTTP_USER_AGENT'];

        if (stripos($userAgent, 'Windows NT') !== false) {
            return 'Windows';
        } elseif (stripos($userAgent, 'Mac OS X') !== false || stripos($userAgent, 'Macintosh') !== false || stripos($userAgent, 'Darwin') !== false) {
            return 'MacOS';
        } elseif (stripos($userAgent, 'Android') !== false) {
            return 'Android';
        } elseif (stripos($userAgent, 'Linux') !== false) {
            return 'Linux';
        } elseif (stripos($userAgent, 'iPhone') !== false) {
            return 'iOS';
        } else {
            return 'Unknown';
        }
    }


}
