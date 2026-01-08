<?php

namespace App\Console\Commands\Task;

use App\Models\Task;
use Illuminate\Console\Command;

class SummaryCommand extends Command
{
    protected $signature = 'task:summary {--json : Output as JSON}';

    protected $description = 'Show a brief summary of tasks (for Claude Code context)';

    public function handle(): int
    {
        $currentTask = Task::inProgress()->focused()->first();
        $pendingTasks = Task::pending()->prioritized()->take(3)->get();
        $recentCompleted = Task::completed()->orderBy('completed_at', 'desc')->take(3)->get();

        $summary = [
            'current_task' => $currentTask ? [
                'id' => $currentTask->id,
                'title' => $currentTask->title,
                'priority' => $currentTask->priority,
                'module' => $currentTask->module,
                'branch' => $currentTask->git_branch,
            ] : null,
            'pending_count' => Task::pending()->count(),
            'next_tasks' => $pendingTasks->map(fn($t) => [
                'id' => $t->id,
                'title' => $t->title,
                'priority' => $t->priority,
            ])->toArray(),
            'recent_completed' => $recentCompleted->map(fn($t) => [
                'id' => $t->id,
                'title' => $t->title,
                'completed_at' => $t->completed_at->toDateTimeString(),
            ])->toArray(),
        ];

        if ($this->option('json')) {
            $this->line(json_encode($summary, JSON_PRETTY_PRINT));
            return self::SUCCESS;
        }

        // Human-readable output
        if ($currentTask) {
            $this->line("Current Task: #{$currentTask->id} - {$currentTask->title}");
            if ($currentTask->git_branch) {
                $this->line("  Branch: {$currentTask->git_branch}");
            }
        } else {
            $this->line('Current Task: None');
        }

        $this->newLine();

        $this->line("Pending Tasks: " . Task::pending()->count());
        if ($pendingTasks->isNotEmpty()) {
            foreach ($pendingTasks as $task) {
                $this->line("  #{$task->id} - {$task->title} ({$task->priority})");
            }
        }

        $this->newLine();

        $this->line("Recently Completed: " . Task::completed()->count() . " total");
        if ($recentCompleted->isNotEmpty()) {
            foreach ($recentCompleted as $task) {
                $this->line("  #{$task->id} - {$task->title}");
            }
        }

        return self::SUCCESS;
    }
}
