<?php

declare(strict_types=1);

namespace App\Services\Portal;

use App\Models\Client;
use Illuminate\Database\Eloquent\Builder;

/**
 * Limits a query to one client's rows (spec sections 5 and 8.5). Every portal query goes through this, so a client
 * cannot read another client's data: a record that belongs to someone else is not found, and the route returns 404.
 */
final class ClientOwnedScope
{
    /**
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    public static function apply(Builder $query, Client $client): Builder
    {
        return $query->where($query->getModel()->qualifyColumn('client_id'), $client->getKey());
    }
}
