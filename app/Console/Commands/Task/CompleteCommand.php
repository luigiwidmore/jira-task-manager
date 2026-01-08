<?php

namespace App\Console\Commands\Task;

use App\Models\Task;
use App\Services\GitService;
use Illuminate\Console\Command;

class CompleteCommand extends Command
{
    protected $signature = 'task:complete
                            {id? : The task ID to complete}
                            {--message= : Commit message}
                            {--no-commit : Don\'t commit changes}
                            {--no-merge : Don\'t merge to main}
                            {--keep-branch : Keep the task branch after merging}';

    protected $description = 'Complete a task with optional git commit and merge';

    public function handle(GitService $git): int
    {
        // Find task to complete
        $taskId = $this->argument('id');

        if ($taskId) {
            $task = Task::find($taskId);
        } else {
            // Find most recently focused in-progress task
            $task = Task::inProgress()->focused()->first();
        }

        if (!$task) {
            $this->error('No in-progress task found. Please specify a task ID.');
            return self::FAILURE;
        }

        $this->info("Completing task #{$task->id}: {$task->title}");
        $this->newLine();

        // Check git repository
        if (!$git->isGitRepository()) {
            $this->warn('Not a git repository. Skipping git operations.');
            $task->markAsCompleted();
            $this->info('Task marked as completed (without git operations)');
            return self::SUCCESS;
        }

        // Show uncommitted changes
        $modifiedFiles = $git->getModifiedFiles();

        if (empty($modifiedFiles)) {
            $this->info('No uncommitted changes to commit.');
        } else {
            $this->line('Uncommitted changes:');
            foreach ($modifiedFiles as $file) {
                $this->line("  {$file['status']} {$file['file']}");
            }
            $this->newLine();

            // Show diff if requested
            if ($this->confirm('Show diff?', false)) {
                $diff = $git->getDiff();
                if (!empty($diff)) {
                    $this->line($diff);
                    $this->newLine();
                }
            }
        }

        // Commit changes
        $shouldCommit = !$this->option('no-commit') && !empty($modifiedFiles);

        if ($shouldCommit) {
            if (!$this->confirm('Commit these changes?', true)) {
                $shouldCommit = false;
            }
        }

        if ($shouldCommit) {
            $message = $this->option('message') ?? $this->ask('Commit message', $task->title);

            $git->stageAll();
            $result = $git->commit($message);

            if ($result->successful()) {
                $this->info('Changes committed successfully');
            } else {
                $this->error('Failed to commit changes: ' . $result->errorOutput());
                return self::FAILURE;
            }
        }

        // Merge to main branch
        $shouldMerge = !$this->option('no-merge') && $task->git_branch;

        if ($shouldMerge) {
            $this->newLine();

            if (!$this->confirm('Merge to main branch?', true)) {
                $shouldMerge = false;
            }
        }

        if ($shouldMerge) {
            $currentBranch = $git->getCurrentBranch();

            // Switch to main
            $checkoutResult = $git->checkoutBranch('main');

            if (!$checkoutResult->successful()) {
                $this->error('Failed to switch to main branch: ' . $checkoutResult->errorOutput());
                return self::FAILURE;
            }

            // Merge task branch
            $mergeResult = $git->merge($task->git_branch);

            if ($mergeResult->successful()) {
                $this->info("Merged {$task->git_branch} into main");

                // Delete branch if requested
                if (!$this->option('keep-branch')) {
                    if ($this->confirm('Delete the task branch?', true)) {
                        $deleteResult = $git->deleteBranch($task->git_branch);

                        if ($deleteResult->successful()) {
                            $this->info("Deleted branch {$task->git_branch}");
                        } else {
                            $this->warn("Failed to delete branch: {$deleteResult->errorOutput()}");
                        }
                    }
                }
            } else {
                $this->error('Failed to merge branch: ' . $mergeResult->errorOutput());
                $this->warn('You may have merge conflicts to resolve.');

                // Switch back to task branch
                $git->checkoutBranch($currentBranch);

                return self::FAILURE;
            }
        }

        // Mark task as completed
        $task->markAsCompleted();

        // Add completion note
        $task->addNote('Task completed', 'user');

        $this->newLine();
        $this->info("Task #{$task->id} completed successfully!");

        // Show summary
        $this->table(
            ['ID', 'Title', 'Status', 'Completed At'],
            [[$task->id, $task->title, $task->status_label, $task->completed_at->format('Y-m-d H:i:s')]]
        );

        // Suggest next task
        $this->newLine();
        $this->info('💡 Use "php artisan task:next" to see what to work on next');

        return self::SUCCESS;
    }
}
