<?php

declare(strict_types=1);

namespace App\Services\AI;

/**
 * A placeholder standing in for a record that does not exist yet.
 *
 * When the AI assistant chains dependent writes inside one turn ("create the
 * project, then the follow-up task"), the task's `project_id` holds
 * `$ref:<pending_action_id>` instead, which is resolved to the created record's
 * id at execution time (PlanReferenceResolver).
 */
final readonly class PlanReference
{
    public const string PREFIX = '$ref:';

    public static function is(mixed $value): bool
    {
        return is_string($value) && str_starts_with($value, self::PREFIX);
    }

    /**
     * The referenced pending action id, or null when $value is not a reference.
     */
    public static function target(mixed $value): ?string
    {
        if (! self::is($value)) {
            return null;
        }

        $target = trim(substr((string) $value, strlen(self::PREFIX)));

        return $target === '' ? null : $target;
    }

    /**
     * $index addresses one record inside a batched proposal: an id alone is
     * ambiguous once a create tool call proposed several records at once.
     */
    public static function to(string $pendingActionId, ?int $index = null): string
    {
        return self::PREFIX . $pendingActionId . ($index === null ? '' : '#' . $index);
    }

    /**
     * The pending action id inside a target, with any `#<index>` suffix removed.
     */
    public static function actionId(string $target): string
    {
        $hash = strpos($target, '#');

        return $hash === false ? $target : substr($target, 0, $hash);
    }

    /**
     * The record index inside a batched proposal, or null when the target names
     * a single-record proposal.
     */
    public static function index(string $target): ?int
    {
        $hash = strpos($target, '#');

        if ($hash === false) {
            return null;
        }

        $index = substr($target, $hash + 1);

        return ctype_digit($index) ? (int) $index : null;
    }

    /**
     * Every reference target anywhere inside the payload, each still carrying any `#<index>` suffix.
     *
     * @param  array<array-key, mixed>  $data
     * @return list<string>
     */
    public static function targetsIn(array $data): array
    {
        $targets = [];

        array_walk_recursive($data, static function (mixed $value) use (&$targets): void {
            $target = self::target($value);

            if ($target !== null) {
                $targets[] = $target;
            }
        });

        return array_values(array_unique($targets));
    }

    /**
     * Rewrite every reference in the payload through $resolver.
     *
     * @param  array<array-key, mixed>  $data
     * @param  callable(string): string  $resolver
     * @return array<array-key, mixed>
     */
    public static function rewrite(array $data, callable $resolver): array
    {
        $rewriteNode = static function (mixed $node) use (&$rewriteNode, $resolver): mixed {
            if (is_array($node)) {
                $out = [];
                foreach ($node as $k => $v) {
                    $out[$k] = $rewriteNode($v);
                }

                return $out;
            }

            if (self::is($node)) {
                $target = self::target($node);

                return $target === null ? $node : $resolver($target);
            }

            return $node;
        };

        /** @var array<array-key, mixed> */
        return $rewriteNode($data);
    }
}
