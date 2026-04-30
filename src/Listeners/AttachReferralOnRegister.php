<?php

namespace A2ZWeb\Affiliate\Listeners;

use A2ZWeb\Affiliate\Services\ReferralAttributor;
use Illuminate\Auth\Events\Registered;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class AttachReferralOnRegister
{
    public function __construct(
        private readonly ReferralAttributor $attributor,
        private readonly Request $request,
    ) {}

    public function handle(Registered $event): void
    {
        $user = $event->user;
        if (! $user instanceof Model) {
            return;
        }

        $this->attributor->attributeNewUser($user, $this->request);
    }
}
