<?php

namespace App\Console\Commands\Task;

use App\Models\Task;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Process;

class PushToGitHubCommand extends Command
{
    protected $signature = 'task:push-github
                            {id? : The task ID to push}
                            {--all : Push all unsynced tasks}
                            {--repo= : GitHub repository (owner/repo)}';

    protected $description = 'Push a local task to GitHub as an issue';

    public function handle(): int
    {
        // Check if gh CLI is available
        if (!$this->isGhCliAvailable()) {
            $this->error('GitHub CLI (gh) is not installed or not authenticated.');
            $this->line('Install: brew install gh');
            $this->line('Authenticate: gh auth login');
            return self::FAILURE;
        }

        // Get repository
        $repo = $this->option('repo') ?? $this->detectRepository();

        if (!$repo) {
            $this->error('Could not detect GitHub repository. Please specify with --repo=owner/repo');
            return self::FAILURE;
        }

        // Push all or single task
        if ($this->option('all')) {
            return $this->pushAllTasks($repo);
        }

        $taskId = $this->argument('id');

        if (!$taskId) {
            $taskId = $this->ask('Enter task ID to push');
        }

        return $this->pushTask($taskId, $repo);
    }

    protected function pushTask(int $taskId, string $repo): int
    {
        $task = Task::find($taskId);

        if (!$task) {
            $this->error("Task #{$taskId} not found");
            return self::FAILURE;
        }

        if ($task->is_synced && $task->external_provider === 'github') {
            $this->warn("Task #{$taskId} is already synced to GitHub issue #{$task->external_id}");

            if (!$this->confirm('Push anyway (will update the issue)?', false)) {
                return self::SUCCESS;
            }
        }

        $this->info("Pushing task #{$task->id} to GitHub...");
        $this->newLine();

        // Prepare issue body
        $body = $this->prepareIssueBody($task);

        // Prepare labels
        $labels = $this->prepareLabels($task);

        // Create GitHub issue
        $command = sprintf(
            'gh issue create --repo %s --title %s --body %s %s',
            escapeshellarg($repo),
            escapeshellarg($task->title),
            escapeshellarg($body),
            $labels ? '--label ' . escapeshellarg($labels) : ''
        );

        $result = Process::run($command);

        if (!$result->successful()) {
            $this->error('Failed to create GitHub issue:');
            $this->line($result->errorOutput());
            return self::FAILURE;
        }

        // Extract issue number from output (gh returns URL)
        $output = trim($result->output());
        if (preg_match('#/issues/(\d+)$#', $output, $matches)) {
            $issueNumber = $matches[1];

            // Update task
            $task->update([
                'external_provider' => 'github',
                'external_id' => $issueNumber,
                'external_url' => $output,
                'is_synced' => true,
                'last_synced_at' => now(),
            ]);

            $this->info("✓ Created GitHub issue #{$issueNumber}");
            $this->line("  {$output}");
            $this->newLine();
            $this->info("Task #{$task->id} synced successfully!");

            return self::SUCCESS;
        }

        $this->error('Could not parse issue number from GitHub response');
        return self::FAILURE;
    }

    protected function pushAllTasks(string $repo): int
    {
        $tasks = Task::unsynced()->get();

        if ($tasks->isEmpty()) {
            $this->info('No unsynced tasks to push');
            return self::SUCCESS;
        }

        $this->info("Found {$tasks->count()} unsynced tasks");
        $this->newLine();

        if (!$this->confirm('Push all to GitHub?', true)) {
            return self::SUCCESS;
        }

        $pushed = 0;
        $failed = 0;

        foreach ($tasks as $task) {
            $this->line("Pushing task #{$task->id}: {$task->title}");

            if ($this->pushTask($task->id, $repo) === self::SUCCESS) {
                $pushed++;
            } else {
                $failed++;
            }

            $this->newLine();
        }

        $this->info("Summary: {$pushed} pushed, {$failed} failed");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }

    protected function prepareIssueBody(Task $task): string
    {
        $body = '';

        // Description
        if ($task->description) {
            $body .= $task->description . "\n\n";
        }

        // Task details
        $body .= "## Task Details\n\n";
        $body .= "- **Priority:** {$task->priority}\n";

        if ($task->module) {
            $body .= "- **Module:** {$task->module}\n";
        }

        if ($task->git_branch) {
            $body .= "- **Branch:** `{$task->git_branch}`\n";
        }

        // Notes
        if ($task->notes()->count() > 0) {
            $body .= "\n## Notes\n\n";
            foreach ($task->notes as $note) {
                $body .= "- {$note->content}\n";
            }
        }

        // Reference
        $body .= "\n---\n";
        $body .= "*Created from local task #{$task->id}*\n";

        return $body;
    }

    protected function prepareLabels(Task $task): string
    {
        $labels = [];

        // Priority label
        switch ($task->priority) {
            case 'urgent':
                $labels[] = 'priority: urgent';
                break;
            case 'high':
                $labels[] = 'priority: high';
                break;
            case 'medium':
                $labels[] = 'priority: medium';
                break;
            case 'low':
                $labels[] = 'priority: low';
                break;
        }

        // Module label
        if ($task->module) {
            $labels[] = 'module: ' . $task->module;
        }

        // Status label
        $labels[] = 'synced-from-local';

        return implode(',', $labels);
    }

    protected function isGhCliAvailable(): bool
    {
        $result = Process::run('gh auth status');
        return $result->successful();
    }

    protected function detectRepository(): ?string
    {
        // Try to detect from git remote
        $result = Process::run('git remote get-url origin');

        if (!$result->successful()) {
            return null;
        }

        $url = trim($result->output());

        // Parse GitHub URL
        if (preg_match('#github\.com[:/]([^/]+/[^/]+?)(\.git)?$#', $url, $matches)) {
            return $matches[1];
        }

        return null;
    }
}
