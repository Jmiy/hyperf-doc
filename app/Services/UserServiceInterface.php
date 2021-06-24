<?php

declare(strict_types=1);

namespace App\Services;

interface UserServiceInterface
{
    public function getInfoById(int $id);
}

