<?php

declare(strict_types=1);
/**
 * Job
 */

namespace App\Jobs;

use Hyperf\AsyncQueue\Job as AsyncQueueJob;

abstract class Job extends AsyncQueueJob
{

}
