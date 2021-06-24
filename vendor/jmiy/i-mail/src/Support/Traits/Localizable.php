<?php

namespace Illuminate\Mail\Support\Traits;

use Hyperf\Utils\ApplicationContext;

trait Localizable
{
    /**
     * Run the callback with the given locale.
     *
     * @param  string  $locale
     * @param  \Closure  $callback
     * @return mixed
     */
    public function withLocale($locale, $callback)
    {
        if (! $locale) {
            return $callback();
        }

        $translator = ApplicationContext::getContainer()->get(TranslatorInterface::class);
        $original = $translator->getLocale();

        try {
            $translator->setLocale($locale);

            return $callback();
        } finally {
            $translator->setLocale($original);
        }
    }
}
