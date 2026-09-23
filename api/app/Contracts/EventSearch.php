<?php

namespace App\Contracts;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface EventSearch
{
    public function search(array $filters, int $perPage = 50): LengthAwarePaginator;
}
