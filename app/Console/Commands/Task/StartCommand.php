<?php

namespace App\Console\Commands\Task;

use App\Models\Task;
use App\Services\GitService;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class StartCommand extends Command
{
    protected $signature = 'task:start
                            {title? : The task title}
                            {--id= : Start an existing task by ID}
                            {--priority=medium : Task priority (low, medium, high, urgent)}
                            {--module= : Module/component name}
                            {--no-branch : Don\'t create git branch}';

    protected $description = 'Start a new task or resume an existing one';

    public function handle(GitService $git): int
    {
        // Check if git repository
        if (!$git->isGitRepository()) {
            $this->error('Not a git repository. Please run this command from a git repository.');
            return self::FAILURE;
        }

        // Start existing task by ID
        if ($this->option('id')) {
            return $this->startExistingTask((int) $this->option('id'), $git);
        }

        // Create new task
        $title = $this->argument('title');

        if (empty($title)) {
            $title = $this->ask('What task are you starting?');
        }

        if (empty($title)) {
            $this->error('Task title is required');
            return self::FAILURE;
        }

        return $this->createNewTask($title, $git);
    }

    protected function createNewTask(string $title, GitService $git): int
    {
        // Check for uncommitted changes
        if ($git->hasUncommittedChanges()) {
            $this->warn('You have uncommitted changes:');
            $files = $git->getModifiedFiles();
            foreach ($files as $file) {
                $this->line("  {$file['status']} {$file['file']}");
            }

            if (!$this->confirm('Do you want to stash these changes and continue?', false)) {
                $this->info('Task not started. Please commit or stash your changes first.');
                return self::FAILURE;
            }

            $stashResult = $git->stash("Auto-stash before starting task: {$title}");
            if ($stashResult->successful()) {
                $this->info('Changes stashed successfully');
            } else {
                $this->error('Failed to stash changes');
                return self::FAILURE;
            }
        }

        // Create task
        $task = Task::create([
            'title' => $title,
            'status' => 'in_progress',
            'priority' => $this->option('priority') ?? 'medium',
            'module' => $this->option('module'),
            'git_branch' => null,
            'created_by' => 'user',
            'focused_at' => now(),
        ]);

        $this->info("Created task #{$task->id}: {$task->title}");

        // Create git branch unless disabled
        if (!$this->option('no-branch')) {
            $branchName = $task->generateBranchName();

            // Check if branch already exists
            if ($git->branchExists($branchName)) {
                $counter = 1;
                while ($git->branchExists("{$branchName}-{$counter}")) {
                    $counter++;
                }
                $branchName = "{$branchName}-{$counter}";
            }

            $result = $git->createBranch($branchName);

            if ($result->successful()) {
                $task->update(['git_branch' => $branchName]);
                $this->info("Created and switched to branch: {$branchName}");
            } else {
                $this->warn("Failed to create git branch: {$result->errorOutput()}");
            }
        }

        // Mark as focused
        $task->markAsFocused();

        $this->newLine();
        $this->line('Task started successfully!');
        $this->table(
            ['ID', 'Title', 'Priority', 'Module', 'Branch'],
            [[$task->id, $task->title, $task->priority, $task->module ?? 'N/A', $task->git_branch ?? 'N/A']]
        );

        $this->newLine();
        $this->info('💡 Use "php artisan task:append-note ' . $task->id . ' \"note\"" to add notes');
        $this->info('💡 Use "php artisan task:complete" when done');

        return self::SUCCESS;
    }

    protected function startExistingTask(int $id, GitService $git): int
    {
        $task = Task::find($id);

        if (!$task) {
            $this->error("Task #{$id} not found");
            return self::FAILURE;
        }

        if ($task->isCompleted()) {
            $this->error("Task #{$id} is already completed");
            return self::FAILURE;
        }

        // Check for uncommitted changes
        if ($git->hasUncommittedChanges()) {
            $this->warn('You have uncommitted changes. Please commit or stash them first.');
            return self::FAILURE;
        }

        // Switch to task branch if it exists
        if ($task->git_branch && $git->branchExists($task->git_branch)) {
            $result = $git->checkoutBranch($task->git_branch);

            if ($result->successful()) {
                $this->info("Switched to branch: {$task->git_branch}");
            } else {
                $this->warn("Failed to switch to branch: {$result->errorOutput()}");
            }
        }

        // Mark as in progress and focused
        $task->markAsInProgress();
        $task->markAsFocused();

        $this->info("Resumed task #{$task->id}: {$task->title}");

        // Show task notes if any
        if ($task->notes()->count() > 0) {
            $this->newLine();
            $this->line('Previous notes:');
            foreach ($task->notes as $note) {
                $this->line("  [{$note->created_at->format('Y-m-d H:i')}] {$note->content}");
            }
        }

        return self::SUCCESS;
    }
}
