<?php

namespace Illuminate\Mail\Contracts;

interface FactoryInterface
{
    /**
     * Get a mailer instance by name.
     *
     * @param  string|null  $name
     * @return \Illuminate\Mail\Mailer
     */
    public function mailer($name = null);
}
