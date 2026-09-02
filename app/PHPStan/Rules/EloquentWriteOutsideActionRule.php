<?php

declare(strict_types=1);

namespace App\PHPStan\Rules;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use PhpParser\Node;
use PhpParser\Node\Expr\CallLike;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\Type\ObjectType;

/**
 * Enforces the project's core write-path convention: all create/update/delete
 * operations must go through domain action classes in app/Actions. Flags Eloquent write
 * calls made from UI and transport surfaces (controllers, MCP tools, Livewire
 * components, Filament resources) where business logic must not live.
 *
 * @implements Rule<CallLike>
 */
final readonly class EloquentWriteOutsideActionRule implements Rule
{
    /**
     * @param  list<string>  $guardedNamespaces
     * @param  list<string>  $writeMethods
     */
    public function __construct(
        private ReflectionProvider $reflectionProvider,
        private array $guardedNamespaces = [
            'App\\Http\\Controllers',
            'App\\Livewire',
            'App\\Filament',
            'App\\Mcp\\Tools',
        ],
        private array $writeMethods = [
            'create', 'createQuietly', 'forceCreate', 'createMany', 'createManyQuietly',
            'save', 'saveQuietly', 'push',
            'update', 'updateQuietly', 'updateOrCreate', 'firstOrCreate',
            'delete', 'deleteQuietly', 'forceDelete', 'destroy', 'restore',
            'insert', 'upsert',
            'attach', 'detach', 'sync', 'syncWithoutDetaching', 'toggle',
        ],
    ) {}

    public function getNodeType(): string
    {
        return CallLike::class;
    }

    /**
     * @return list<IdentifierRuleError>
     */
    public function processNode(Node $node, Scope $scope): array
    {
        if (! $node instanceof MethodCall && ! $node instanceof StaticCall) {
            return [];
        }

        $namespace = $scope->getNamespace();
        if ($namespace === null || ! $this->isGuardedNamespace($namespace)) {
            return [];
        }

        $methodName = $this->resolveMethodName($node);
        if ($methodName === null || ! in_array($methodName, $this->writeMethods, true)) {
            return [];
        }

        if (! $this->isEloquentTarget($node, $scope)) {
            return [];
        }

        return [
            RuleErrorBuilder::message(
                sprintf(
                    'Direct Eloquent write method "%s()" called in "%s". All database mutations must go through dedicated Action classes in app/Actions.',
                    $methodName,
                    $namespace,
                )
            )
            ->identifier('architecture.eloquentWriteOutsideAction')
            ->build(),
        ];
    }

    private function isGuardedNamespace(string $namespace): bool
    {
        foreach ($this->guardedNamespaces as $guarded) {
            if ($namespace === $guarded || str_starts_with($namespace, $guarded . '\\')) {
                return true;
            }
        }

        return false;
    }

    private function resolveMethodName(MethodCall|StaticCall $node): ?string
    {
        if ($node->name instanceof Identifier) {
            return $node->name->name;
        }

        return null;
    }

    private function isEloquentTarget(MethodCall|StaticCall $node, Scope $scope): bool
    {
        $modelType = new ObjectType(Model::class);
        $builderType = new ObjectType(Builder::class);
        $relationType = new ObjectType(Relation::class);

        if ($node instanceof MethodCall) {
            $calledOnType = $scope->getType($node->var);

            return $modelType->isSuperTypeOf($calledOnType)->yes()
                || $builderType->isSuperTypeOf($calledOnType)->yes()
                || $relationType->isSuperTypeOf($calledOnType)->yes();
        }

        if ($node->class instanceof Name) {
            $className = $scope->resolveName($node->class);
            if (! $this->reflectionProvider->hasClass($className)) {
                return false;
            }

            $classReflection = $this->reflectionProvider->getClass($className);

            return $classReflection->isSubclassOf(Model::class);
        }

        return false;
    }
}
