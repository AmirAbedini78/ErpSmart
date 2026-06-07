<?php

return [
    'name' => 'Saas',
    'encryption_key' => env('APP_KEY', 'Bae!dY$2H0mN6%@k%L!hW7tC#j2n%J5'),
    'verification' => [
        'get_code_url' => 'https://envato.toofasthost.com/api/givemecode',
        'validate_code_url' => 'https://envato.toofasthost.com/api/validate',
        'register_code_url' => 'https://envato.toofasthost.com/api/register',
        'evanto_product_id' => 58558714,
    ]
];
