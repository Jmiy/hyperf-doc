<?php

namespace Illuminate\Mail;

use Hyperf\Utils\ApplicationContext;
use Illuminate\Mail\Contracts\FactoryInterface;
use Illuminate\Mail\Contracts\Mailable as MailableContract;
use Hyperf\AsyncQueue\Job as AsyncQueueJob;

class SendQueuedMailable extends AsyncQueueJob
{
    /**
     * The mailable message instance.
     *
     * @var \Illuminate\Mail\Contracts\Mailable
     */
    public $mailable;

    /**
     * @var int
     */
    protected $maxAttempts = 0;

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public $tries;

    /**
     * The number of seconds the job can run before timing out.
     *
     * @var int
     */
    public $timeout;

    /**
     * Create a new job instance.
     *
     * @param  \Illuminate\Mail\Contracts\Mailable  $mailable
     * @return void
     */
    public function __construct(MailableContract $mailable)
    {
        $this->mailable = $mailable;
        $this->tries = property_exists($mailable, 'tries') ? $mailable->tries : null;
        $this->timeout = property_exists($mailable, 'timeout') ? $mailable->timeout : null;

        $this->maxAttempts = property_exists($mailable, 'tries') ? $mailable->tries : $this->maxAttempts;
    }

    /**
     * Handle the queued job.
     *
     * @return void
     */
    public function handle()
    {
        $this->mailable->send(ApplicationContext::getContainer()->get(FactoryInterface::class));
    }

    /**
     * Get the display name for the queued job.
     *
     * @return string
     */
    public function displayName()
    {
        return get_class($this->mailable);
    }

    /**
     * Call the failed method on the mailable instance.
     *
     * @param  \Throwable  $e
     * @return void
     */
    public function failed($e)
    {
        if (method_exists($this->mailable, 'failed')) {
            $this->mailable->failed($e);
        }
    }

    /**
     * Get the retry delay for the mailable object.
     *
     * @return mixed
     */
    public function retryAfter()
    {
        if (! method_exists($this->mailable, 'retryAfter') && ! isset($this->mailable->retryAfter)) {
            return;
        }

        return $this->mailable->retryAfter ?? $this->mailable->retryAfter();
    }

    /**
     * Prepare the instance for cloning.
     *
     * @return void
     */
    public function __clone()
    {
        $this->mailable = clone $this->mailable;
    }
}
