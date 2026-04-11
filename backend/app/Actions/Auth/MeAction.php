<?php

namespace App\Actions\Auth;

use App\Models\User;

class MeAction
{
    public function __construct(
        private readonly BuildUserDataAction $buildUserData,
    ) {}

    public function execute(User $user): array
    {
        return $this->buildUserData->execute($user);
    }
}
