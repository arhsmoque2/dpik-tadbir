<?php

use App\Mcp\Tools\Interactive\AskUserQuestionTool;
use App\Mcp\Tools\Interactive\ProposeActionCardTool;
use App\Mcp\Tools\Notes\CreatePersonalNoteTool;

// ADR-021: AgentService used to decide which tool calls must suspend the
// turn for executive approval via a hardcoded name literal
// (`in_array($name, ['ask_user_question', 'propose_action_card'])`). That's
// now declared on each tool itself, so this asserts the declaration rather
// than the removed literal.
test('interactive tools declare requiresConfirmation, ordinary tools do not', function () {
    expect(app(AskUserQuestionTool::class)->requiresConfirmation())->toBeTrue();
    expect(app(ProposeActionCardTool::class)->requiresConfirmation())->toBeTrue();
    expect(app(CreatePersonalNoteTool::class)->requiresConfirmation())->toBeFalse();
});
