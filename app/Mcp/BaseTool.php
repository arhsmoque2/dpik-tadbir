<?php

namespace App\Mcp;

abstract class BaseTool
{
    protected string $name;

    protected string $description;

    public function getName(): string
    {
        return $this->name;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    /**
     * @return array<string, mixed>
     */
    abstract public function schema(): array;

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    abstract public function handle(array $arguments): array;
}
