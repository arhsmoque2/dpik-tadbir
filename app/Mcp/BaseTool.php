<?php

namespace App\Mcp;

abstract class BaseTool
{
    protected string $name;

    protected string $description;

    /**
     * Declares that this tool's calls must suspend the agent turn for
     * explicit executive approval before/instead of executing further
     * (ADR-021) — previously AgentService hardcoded a name-literal check
     * (`ask_user_question`, `propose_action_card`) to decide this, so any
     * new tool needing the same treatment required an AgentService edit.
     * Declaring it on the tool itself means the loop only needs to ask
     * each tool, not know its name in advance.
     */
    protected bool $requiresConfirmation = false;

    public function getName(): string
    {
        return $this->name;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function requiresConfirmation(): bool
    {
        return $this->requiresConfirmation;
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
