<?php

namespace App\Mcp\Tools\Notes;

use App\Mcp\BaseTool;
use App\Models\PersonalNote;
use RuntimeException;

class CreatePersonalNoteTool extends BaseTool
{
    protected string $name = 'create_personal_note';

    protected string $description = 'Stores a private personal note scoped strictly to the authenticated executive.';

    /**
     * @return array<string, mixed>
     */
    public function schema(): array
    {
        return [
            'type' => 'object',
            'required' => ['title', 'content'],
            'properties' => [
                'title' => ['type' => 'string'],
                'content' => ['type' => 'string'],
                'project_code' => ['type' => 'string'],
                'tags' => ['type' => 'array', 'items' => ['type' => 'string']],
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
            throw new RuntimeException('Cannot create a personal note outside an authenticated executive session.');
        }
        $userId = $user->id;
        $title = (string) ($arguments['title'] ?? 'Untitled Note');
        $content = (string) ($arguments['content'] ?? '');
        $projectCode = isset($arguments['project_code']) ? (string) $arguments['project_code'] : null;
        /** @var list<string> $tags */
        $tags = (array) ($arguments['tags'] ?? []);

        $note = PersonalNote::create([
            'user_id' => $userId,
            'project_code' => $projectCode,
            'title' => $title,
            'content' => $content,
            'tags' => $tags,
        ]);

        return [
            'status' => 'created',
            'id' => $note->id,
            'title' => $note->title,
        ];
    }
}
