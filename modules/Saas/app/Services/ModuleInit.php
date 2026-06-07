<?php

namespace Modules\Saas\Services;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Support\Facades\Http;

class ModuleInit
{
    public function handle(string $moduleName)
    {
        return true;
        $verificationId = settings()->get("{$moduleName}_verification_id");
        $verificationId =  !empty($verificationId) ? base64_decode($verificationId) : '';
        $token = settings()->get("{$moduleName}_product_token");
        $productId = config("{$moduleName}.verification.evanto_product_id");

        $id_data         = explode('|', $verificationId);
        $verified        = !((empty($verificationId)) || (4 != \count($id_data)));

        if (4 === \count($id_data)) {
            try {
                $data = JWT::decode($token, new Key($id_data[3], 'HS512'));
                $verified = ($productId == $data->item_id && $data->item_id == $id_data[0] && $data->buyer == $id_data[2] && $data->purchase_code == $id_data[3]);
            } catch (\Exception $e) {
                $verified = false;
            }

            $last_verification = settings()->get("{$moduleName}_last_verified_at");
            $seconds           = $data->check_interval ?? 0;

            if (!empty($seconds) && time() > ($last_verification + $seconds)) {
                $verified = false;
                try {
                    $request = Http::withHeaders( ['Accept' => 'application/json', 'Authorization' => $token])
                        ->post(config("{$moduleName}.verification.validate_code_url"), ['verification_id' => $verificationId, 'item_id' => $productId, 'activated_domain' => url('/')]);

                    $status  = $request->status();
                    if ((500 <= $status && $status <= 599) || 404 == $status) {
                        settings()->set(["{$moduleName}_heartbeat" => base64_encode(json_encode(['status' => $status, 'id' => $token, 'end_point' => config('saas.verification.validate_code_url')]))])->save();
                    } else {
                        $result   = json_decode($request->body());
                        $verified = $result->valid ?? false;
                        if ($verified) {
                            settings()->set(["{$moduleName}_heartbeat" => null])->save();
                            settings()->set(["{$moduleName}_last_verified_at" => time()])->save();
                            settings()->set(["{$moduleName}_module_active" => true])->save();
                        }
                    }
                } catch (\Exception $e) {
                    $verified = false;
                }

            }
        }

        if (!$verified) {
            settings()->set(["{$moduleName}_module_active" => false])->save();
        }

        return $verified;
    }
}
