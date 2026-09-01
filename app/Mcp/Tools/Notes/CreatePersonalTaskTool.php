<?php

namespace App\Mcp\Tools\Notes;

use App\Mcp\BaseTool;
use App\Models\PersonalTask;
use RuntimeException;

class CreatePersonalTaskTool extends BaseTool
{
    protected string $name = 'create_personal_task';

    protected string $description = 'Creates a private personal follow-up task scoped strictly to the authenticated executive.';

    /**
     * @return array<string, mixed>
     */
    public function schema(): array
    {
        return [
            'type' => 'object',
            'required' => ['title'],
            'properties' => [
                'title' => ['type' => 'string'],
                'description' => ['type' => 'string'],
                'project_code' => ['type' => 'string'],
                'due_date' => ['type' => 'string'],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    public function handle(array $arguments): array
    {
        $user = auth()->user();
        if ($user === null) {
            throw new RuntimeException('Cannot create a personal task outside an authenticated executive session.');
        }
        $userId = $user->id;
        $title = (string) ($arguments['title'] ?? 'Untitled Task');
        $description = isset($arguments['description']) ? (string) $arguments['description'] : null;
        $projectCode = isset($arguments['project_code']) ? (string) $arguments['project_code'] : null;
        $dueDate = isset($arguments['due_date']) ? (string) $arguments['due_date'] : null;

        $task = PersonalTask::create([
            'user_id' => $userId,
            'project_code' => $projectCode,
            'title' => $title,
            'description' => $description,
            'due_date' => $dueDate,
            'status' => 'pending',
        ]);

        return [
            'status' => 'created',
            'id' => $task->id,
            'title' => $task->title,
        ];
    }
}
