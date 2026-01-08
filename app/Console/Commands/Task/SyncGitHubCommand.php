<?php

namespace App\Console\Commands\Task;

use Illuminate\Console\Command;

class SyncGitHubCommand extends Command
{
    protected $signature = 'task:sync-github
                            {--push : Only push local tasks to GitHub}
                            {--pull : Only pull issues from GitHub}
                            {--repo= : GitHub repository (owner/repo)}';

    protected $description = 'Bidirectional sync between local tasks and GitHub issues';

    public function handle(): int
    {
        $pushOnly = $this->option('push');
        $pullOnly = $this->option('pull');

        $this->info('GitHub Sync');
        $this->line(str_repeat('=', 40));
        $this->newLine();

        // Pull from GitHub (unless push-only)
        if (!$pushOnly) {
            $this->line('📥 Pulling from GitHub...');
            $pullResult = $this->call('task:pull-github', array_filter([
                '--repo' => $this->option('repo'),
            ]));

            if ($pullResult !== self::SUCCESS) {
                $this->error('Pull from GitHub failed');
                return self::FAILURE;
            }

            $this->newLine();
        }

        // Push to GitHub (unless pull-only)
        if (!$pullOnly) {
            $this->line('📤 Pushing to GitHub...');
            $pushResult = $this->call('task:push-github', array_filter([
                '--all' => true,
                '--repo' => $this->option('repo'),
            ]));

            if ($pushResult !== self::SUCCESS) {
                $this->error('Push to GitHub failed');
                return self::FAILURE;
            }

            $this->newLine();
        }

        $this->info('✓ Sync completed successfully!');

        return self::SUCCESS;
    }
}
