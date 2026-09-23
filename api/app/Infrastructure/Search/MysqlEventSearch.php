<?php

namespace App\Infrastructure\Search;

use App\Contracts\EventSearch;
use App\Enums\EventStatus;
use App\Exceptions\SearchUnavailableException;
use App\Models\Event;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class MysqlEventSearch implements EventSearch
{
    public function search(array $filters, int $perPage = 50): LengthAwarePaginator
    {
        if (! config('search.available')) {
            throw new SearchUnavailableException('Event search is temporarily unavailable.');
        }

        $query = Event::query()
            ->where('status', EventStatus::PUBLISHED->value);

        if (! empty($filters['name'])) {
            $query->where('name', 'like', $this->prefix($filters['name']));
        }

        if (! empty($filters['location'])) {
            $query->where('location', 'like', $this->prefix($filters['location']));
        }

        if (! empty($filters['starts_from'])) {
            $query->where('starts_at', '>=', $filters['starts_from']);
        }

        if (! empty($filters['starts_to'])) {
            $query->where('starts_at', '<=', $filters['starts_to']);
        }

        return $query
            ->orderBy('starts_at')
            ->orderBy('id')
            ->paginate($perPage)
            ->withQueryString();
    }

    private function prefix(string $value): string
    {
        return addcslashes(trim($value), '%_\\') . '%';
    }
}
