<?php

namespace App\Mcp;

use App\Mcp\Tools\Interactive\AskUserQuestionTool;
use App\Mcp\Tools\Interactive\ProposeActionCardTool;
use App\Mcp\Tools\Memory\CommitProjectRegisterTool;
use App\Mcp\Tools\Memory\QueryProjectRegisterTool;
use App\Mcp\Tools\Notes\CreatePersonalNoteTool;
use App\Mcp\Tools\Notes\CreatePersonalTaskTool;
use App\Mcp\Tools\Outlook\OutlookCreateDraftTool;
use App\Mcp\Tools\Outlook\OutlookForwardTool;
use App\Mcp\Tools\Outlook\OutlookListInboxDeltaTool;
use App\Mcp\Tools\Outlook\OutlookReadMessageTool;
use App\Mcp\Tools\Outlook\OutlookReplyTool;
use App\Mcp\Tools\Outlook\OutlookSearchMailTool;
use InvalidArgumentException;

class ToolRegistry
{
    /**
     * @var array<string, BaseTool>
     */
    protected array $tools = [];

    public function __construct(
        AskUserQuestionTool $askQuestion,
        ProposeActionCardTool $proposeActionCard,
        OutlookCreateDraftTool $createDraft,
        OutlookReplyTool $reply,
        OutlookForwardTool $forward,
        OutlookSearchMailTool $searchMail,
        OutlookListInboxDeltaTool $listInboxDelta,
        OutlookReadMessageTool $readMessage,
        QueryProjectRegisterTool $queryRegister,
        CommitProjectRegisterTool $commitRegister,
        CreatePersonalNoteTool $createNote,
        CreatePersonalTaskTool $createTask,
    ) {
        $this->register($askQuestion);
        $this->register($proposeActionCard);
        $this->register($createDraft);
        $this->register($reply);
        $this->register($forward);
        $this->register($searchMail);
        $this->register($listInboxDelta);
        $this->register($readMessage);
        $this->register($queryRegister);
        $this->register($commitRegister);
        $this->register($createNote);
        $this->register($createTask);
    }

    public function register(BaseTool $tool): void
    {
        $this->tools[$tool->getName()] = $tool;
    }

    public function has(string $name): bool
    {
        return isset($this->tools[$name]);
    }

    public function get(string $name): BaseTool
    {
        if (! isset($this->tools[$name])) {
            throw new InvalidArgumentException("Tool [{$name}] not registered in ToolRegistry.");
        }

        return $this->tools[$name];
    }

    /**
     * @return array<string, BaseTool>
     */
    public function all(): array
    {
        return $this->tools;
    }

    /**
     * Returns tool definitions formatted for LLM schema declarations.
     *
     * @return list<array{name: string, description: string, parameters: array<string, mixed>}>
     */
    public function getLlmToolDefinitions(): array
    {
        $definitions = [];

        foreach ($this->tools as $tool) {
            $definitions[] = [
                'name' => $tool->getName(),
                'description' => $tool->getDescription(),
                'parameters' => $tool->schema(),
            ];
        }

        return $definitions;
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    public function execute(string $toolName, array $arguments): array
    {
        return $this->get($toolName)->handle($arguments);
    }
}
