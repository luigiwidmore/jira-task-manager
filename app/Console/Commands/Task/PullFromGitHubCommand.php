<?php

namespace App\Console\Commands\Task;

use App\Models\Task;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Process;

class PullFromGitHubCommand extends Command
{
    protected $signature = 'task:pull-github
                            {--repo= : GitHub repository (owner/repo)}
                            {--limit=20 : Number of issues to fetch}
                            {--state=all : Issue state (open, closed, all)}';

    protected $description = 'Pull GitHub issues into local tasks';

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

        $this->info("Fetching issues from {$repo}...");

        // Fetch issues using gh CLI
        $command = sprintf(
            'gh issue list --repo %s --limit %d --state %s --json number,title,body,state,labels,updatedAt',
            escapeshellarg($repo),
            $this->option('limit'),
            $this->option('state')
        );

        $result = Process::run($command);

        if (!$result->successful()) {
            $this->error('Failed to fetch GitHub issues:');
            $this->line($result->errorOutput());
            return self::FAILURE;
        }

        $issues = json_decode($result->output(), true);

        if (empty($issues)) {
            $this->info('No issues found');
            return self::SUCCESS;
        }

        $this->info("Found {$issues->count()} issues");
        $this->newLine();

        $created = 0;
        $updated = 0;
        $skipped = 0;

        foreach ($issues as $issue) {
            $existingTask = Task::where('external_provider', 'github')
                ->where('external_id', $issue['number'])
                ->first();

            if ($existingTask) {
                // Update existing task
                if ($this->shouldUpdate($existingTask, $issue)) {
                    $this->updateTaskFromIssue($existingTask, $issue);
                    $updated++;
                    $this->line("✓ Updated task #{$existingTask->id} from issue #{$issue['number']}");
                } else {
                    $skipped++;
                }
            } else {
                // Create new task
                $task = $this->createTaskFromIssue($issue, $repo);
                $created++;
                $this->line("✓ Created task #{$task->id} from issue #{$issue['number']}");
            }
        }

        $this->newLine();
        $this->info("Summary: {$created} created, {$updated} updated, {$skipped} skipped");

        return self::SUCCESS;
    }

    protected function createTaskFromIssue(array $issue, string $repo): Task
    {
        // Extract module from labels
        $module = null;
        foreach ($issue['labels'] ?? [] as $label) {
            if (str_starts_with($label['name'], 'module: ')) {
                $module = str_replace('module: ', '', $label['name']);
                break;
            }
        }

        // Extract priority from labels
        $priority = 'medium';
        foreach ($issue['labels'] ?? [] as $label) {
            if (str_starts_with($label['name'], 'priority: ')) {
                $priority = str_replace('priority: ', '', $label['name']);
                break;
            }
        }

        // Determine status from issue state
        $status = $issue['state'] === 'OPEN' ? 'pending' : 'completed';
        $completedAt = $issue['state'] === 'CLOSED' ? now() : null;

        return Task::create([
            'title' => $issue['title'],
            'description' => $issue['body'] ?? null,
            'status' => $status,
            'priority' => $priority,
            'module' => $module,
            'external_provider' => 'github',
            'external_id' => (string) $issue['number'],
            'external_url' => "https://github.com/{$repo}/issues/{$issue['number']}",
            'is_synced' => true,
            'last_synced_at' => now(),
            'created_by' => 'github',
            'completed_at' => $completedAt,
        ]);
    }

    protected function updateTaskFromIssue(Task $task, array $issue): void
    {
        $updates = [];

        // Update status if changed
        $newStatus = $issue['state'] === 'OPEN' ? 'in_progress' : 'completed';
        if ($task->status !== $newStatus && $task->status !== 'completed') {
            $updates['status'] = $newStatus;
            if ($newStatus === 'completed') {
                $updates['completed_at'] = now();
            }
        }

        // Update title if changed
        if ($task->title !== $issue['title']) {
            $updates['title'] = $issue['title'];
        }

        // Update sync timestamp
        $updates['last_synced_at'] = now();

        if (!empty($updates)) {
            $task->update($updates);
        }
    }

    protected function shouldUpdate(Task $task, array $issue): bool
    {
        // Check if issue was updated after last sync
        if (!$task->last_synced_at) {
            return true;
        }

        $issueUpdatedAt = new \DateTime($issue['updatedAt']);
        return $issueUpdatedAt > $task->last_synced_at;
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
