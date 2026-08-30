<?php

namespace Tests\Support;

use Illuminate\Support\Facades\DB;

trait DatabaseQueryCounter
{
    /**
     * Assert that the given callback executes at most $max database queries.
     */
    public function assertMaxQueries(int $max, callable $callback, string $message = ''): mixed
    {
        $queries = [];
        DB::listen(function ($query) use (&$queries) {
            $queries[] = $query->sql;
        });

        $result = $callback();

        $count = count($queries);
        $customMessage = $message ?: "Expected at most {$max} queries, but {$count} were executed.\nQueries:\n".implode("\n", $queries);

        $this->assertLessThanOrEqual($max, $count, $customMessage);

        return $result;
    }

    /**
     * Assert that the given callback executes zero database queries.
     */
    public function assertNoQueries(callable $callback, string $message = ''): mixed
    {
        return $this->assertMaxQueries(0, $callback, $message ?: 'Expected no database queries to be executed.');
    }

    /**
     * Count the number of queries executed by the given callback.
     */
    public function countQueries(callable $callback): int
    {
        $count = 0;
        DB::listen(function () use (&$count) {
            $count++;
        });

        $callback();

        return $count;
    }
}
