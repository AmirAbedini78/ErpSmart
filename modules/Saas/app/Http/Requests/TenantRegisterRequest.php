<?php

namespace Modules\Saas\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Core\Facades\ReCaptcha;
use Modules\Core\Rules\ValidRecaptchaRule;

class TenantRegisterRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\Rule|array|string>
     */
    public function rules()
{
    return [
        'first_name' => 'required|string|max:255',
        'last_name' => 'required|string|max:255',
        'email' => 'required|email|unique:contacts,email',
        'company_name' => 'required|string|max:255|unique:tenants,name',
        'subdomain' => ['nullable', 'string', 'regex:/^[a-zA-Z0-9]+([-.][a-zA-Z0-9]+)*$/', 'unique:tenants,subdomain'],
        'domain' => ['nullable', 'regex:/^(?!:\/\/)([a-zA-Z0-9-_]+\.)*[a-zA-Z0-9][a-zA-Z0-9-_]+\.[a-zA-Z]{2,}$/', 'unique:tenants,domain'],
        'street' => 'nullable|string|max:255',
        'postal_code' => 'nullable|string|max:20',
        'city' => 'nullable|string|max:100',
        'state' => 'nullable|string|max:100',
        'package' => 'required|array',
        'package.id' => 'required|exists:packages,id',
        'country_id' => 'nullable',
        ...ReCaptcha::shouldShow() ?
            ['g-recaptcha-response' => ['required', new ValidRecaptchaRule]] :
            [],
    ];
}

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }
}
