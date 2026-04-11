<?php

namespace App\Actions\Auth;

use App\Models\RegistrationRequest;
use Illuminate\Support\Facades\Hash;

class SubmitRegistrationRequestAction
{
    /**
     * @param  array{name: string, whatsapp: string, password: string, email?: string|null, location?: string|null, farm_name?: string|null}  $data
     */
    public function execute(array $data): RegistrationRequest
    {
        return RegistrationRequest::create([
            'name'          => $data['name'],
            'whatsapp'      => $data['whatsapp'],
            'password_hash' => Hash::make($data['password']),
            'email'         => $data['email'] ?? null,
            'location'      => $data['location'] ?? null,
            'farm_name'     => $data['farm_name'] ?? null,
            'status'        => 'pending',
        ]);
    }
}
