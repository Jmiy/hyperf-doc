<?php

namespace App\Services\Payment\Contracts;

interface Provider
{
    /**
     * Redirect the user to the pay page for the provider.
     *
     * @return \Symfony\Component\HttpFoundation\RedirectResponse
     */
    public function redirect($orderData);

    /**
     * Get the User instance for the authenticated user.
     *
     * @return \Laravel\Socialite\Contracts\User
     */
    public function callback($requestData);
    
    public function notify();
    
    public function refund($refundData);
}
