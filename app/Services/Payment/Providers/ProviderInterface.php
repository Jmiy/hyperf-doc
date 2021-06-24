<?php

namespace App\Services\Payment\Providers;

interface ProviderInterface
{
    /**
     * Redirect the user to the authentication page for the provider.
     *
     * @return \Symfony\Component\HttpFoundation\RedirectResponse
     */
    public function redirect($orderData);

    /**
     * Get the User instance for the authenticated user.
     *
     * @return \Laravel\Socialite\Two\User
     */
    public function callback($requestData);
    
    public function notify();
    
    public function refund($refundData);
}
