<?php

namespace App\Services;

use Illuminate\Support\Facades\Process;
use Illuminate\Process\ProcessResult;

class GitService
{
    /**
     * Get the current git branch
     */
    public function getCurrentBranch(): ?string
    {
        $result = $this->run('git branch --show-current');

        return $result->successful() ? trim($result->output()) : null;
    }

    /**
     * Check if a branch exists
     */
    public function branchExists(string $branchName): bool
    {
        $result = $this->run("git rev-parse --verify {$branchName}");

        return $result->successful();
    }

    /**
     * Create and checkout a new branch
     */
    public function createBranch(string $branchName): ProcessResult
    {
        return $this->run("git checkout -b {$branchName}");
    }

    /**
     * Checkout an existing branch
     */
    public function checkoutBranch(string $branchName): ProcessResult
    {
        return $this->run("git checkout {$branchName}");
    }

    /**
     * Get list of modified files
     */
    public function getModifiedFiles(): array
    {
        $result = $this->run('git status --porcelain');

        if (!$result->successful()) {
            return [];
        }

        $files = [];
        $lines = explode("\n", trim($result->output()));

        foreach ($lines as $line) {
            if (empty($line)) {
                continue;
            }

            // Format: "XY filename"
            // X = staged status, Y = unstaged status
            $files[] = [
                'status' => substr($line, 0, 2),
                'file' => trim(substr($line, 3)),
            ];
        }

        return $files;
    }

    /**
     * Get git diff
     */
    public function getDiff(bool $staged = false): string
    {
        $command = $staged ? 'git diff --staged' : 'git diff';
        $result = $this->run($command);

        return $result->successful() ? $result->output() : '';
    }

    /**
     * Stage all changes
     */
    public function stageAll(): ProcessResult
    {
        return $this->run('git add .');
    }

    /**
     * Stage specific file
     */
    public function stageFile(string $file): ProcessResult
    {
        return $this->run("git add {$file}");
    }

    /**
     * Commit changes
     */
    public function commit(string $message): ProcessResult
    {
        $escapedMessage = escapeshellarg($message);
        return $this->run("git commit -m {$escapedMessage}");
    }

    /**
     * Merge a branch into current branch
     */
    public function merge(string $branchName): ProcessResult
    {
        return $this->run("git merge {$branchName}");
    }

    /**
     * Delete a branch
     */
    public function deleteBranch(string $branchName, bool $force = false): ProcessResult
    {
        $flag = $force ? '-D' : '-d';
        return $this->run("git branch {$flag} {$branchName}");
    }

    /**
     * Check if working directory is clean
     */
    public function isClean(): bool
    {
        $result = $this->run('git status --porcelain');

        return $result->successful() && empty(trim($result->output()));
    }

    /**
     * Get uncommitted changes count
     */
    public function getUncommittedChangesCount(): int
    {
        return count($this->getModifiedFiles());
    }

    /**
     * Check if current directory is a git repository
     */
    public function isGitRepository(): bool
    {
        $result = $this->run('git rev-parse --is-inside-work-tree');

        return $result->successful() && trim($result->output()) === 'true';
    }

    /**
     * Get the root directory of the git repository
     */
    public function getRepositoryRoot(): ?string
    {
        $result = $this->run('git rev-parse --show-toplevel');

        return $result->successful() ? trim($result->output()) : null;
    }

    /**
     * Get recent commit log
     */
    public function getRecentCommits(int $count = 10): array
    {
        $result = $this->run("git log -{$count} --pretty=format:'%H|%an|%ae|%s|%ai'");

        if (!$result->successful()) {
            return [];
        }

        $commits = [];
        $lines = explode("\n", trim($result->output()));

        foreach ($lines as $line) {
            if (empty($line)) {
                continue;
            }

            [$hash, $author, $email, $subject, $date] = explode('|', $line);

            $commits[] = [
                'hash' => $hash,
                'author' => $author,
                'email' => $email,
                'subject' => $subject,
                'date' => $date,
            ];
        }

        return $commits;
    }

    /**
     * Check if there are uncommitted changes
     */
    public function hasUncommittedChanges(): bool
    {
        return !$this->isClean();
    }

    /**
     * Stash changes
     */
    public function stash(string $message = ''): ProcessResult
    {
        $command = empty($message) ? 'git stash' : 'git stash push -m ' . escapeshellarg($message);
        return $this->run($command);
    }

    /**
     * Pop stashed changes
     */
    public function stashPop(): ProcessResult
    {
        return $this->run('git stash pop');
    }

    /**
     * Run a git command
     */
    protected function run(string $command): ProcessResult
    {
        return Process::run($command);
    }
}
