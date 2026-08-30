<?php

namespace App\Mcp\Tools\Interactive;

use App\Mcp\BaseTool;

class AskUserQuestionTool extends BaseTool
{
    protected string $name = 'ask_user_question';

    protected string $description = 'Presents a multiple-choice question modal to the executive with non-exclusive freeform notes and escape hatches (Skip/Cancel).';

    /**
     * @return array<string, mixed>
     */
    public function schema(): array
    {
        return [
            'type' => 'object',
            'required' => ['question', 'options'],
            'properties' => [
                'question' => ['type' => 'string'],
                'options' => ['type' => 'array', 'items' => ['type' => 'string']],
                'is_multi_select' => ['type' => 'boolean', 'default' => false],
                'allow_custom_input' => ['type' => 'boolean', 'default' => true],
                'custom_input_placeholder' => ['type' => 'string'],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    public function handle(array $arguments): array
    {
        return [
            'status' => 'suspended',
            'state' => 'AWAITING_USER_INPUT',
            'payload' => $arguments,
        ];
    }
}
