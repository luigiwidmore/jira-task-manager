<?php

namespace App\Console\Commands\Task;

use App\Models\Task;
use App\Services\GitService;
use Illuminate\Console\Command;

class CaptureCommand extends Command
{
    protected $signature = 'task:capture
                            {title : The task title}
                            {--description= : Task description}
                            {--priority=medium : Task priority}
                            {--module= : Module/component name}
                            {--context= : Additional context (e.g., current branch)}
                            {--source=claude : Source of the task (claude or user)}';

    protected $description = 'Quickly capture a task (for use by Claude Code)';

    public function handle(GitService $git): int
    {
        $title = $this->argument('title');

        // Auto-detect module from context if not provided
        $module = $this->option('module');

        if (!$module && $git->isGitRepository()) {
            $currentBranch = $git->getCurrentBranch();
            // Try to extract module from branch name (e.g., feature/auth-login -> auth)
            if (preg_match('#^(?:feature|task|fix)/([^-]+)#', $currentBranch, $matches)) {
                $module = $matches[1];
            }
        }

        // Create task
        $task = Task::create([
            'title' => $title,
            'description' => $this->option('description'),
            'status' => 'pending',
            'priority' => $this->option('priority'),
            'module' => $module,
            'created_by' => $this->option('source'),
        ]);

        // Add context as note if provided
        if ($context = $this->option('context')) {
            $task->addNote("Context: {$context}", $this->option('source'));
        }

        $this->info("Task #{$task->id} captured: {$task->title}");

        if ($module) {
            $this->line("Module: {$module}");
        }

        $this->newLine();
        $this->info('💡 Start this task with: php artisan task:start --id=' . $task->id);

        return self::SUCCESS;
    }
}
