<?php

namespace App\Console\Commands\Task;

use App\Models\Task;
use Illuminate\Console\Command;

class NextCommand extends Command
{
    protected $signature = 'task:next
                            {--module= : Filter by module}
                            {--priority= : Filter by priority}
                            {--start : Automatically start the next task}';

    protected $description = 'Show the next recommended task to work on';

    public function handle(): int
    {
        $query = Task::pending()->prioritized();

        // Apply filters
        if ($module = $this->option('module')) {
            $query->where('module', $module);
        }

        if ($priority = $this->option('priority')) {
            $query->where('priority', $priority);
        }

        $nextTask = $query->first();

        if (!$nextTask) {
            $this->info('No pending tasks found! 🎉');
            $this->newLine();
            $this->info('💡 Create a new task with: php artisan task:start "Task title"');
            return self::SUCCESS;
        }

        $this->line('Next recommended task:');
        $this->newLine();

        $this->table(
            ['ID', 'Title', 'Priority', 'Module', 'Created'],
            [[
                $nextTask->id,
                $nextTask->title,
                $nextTask->priority_label,
                $nextTask->module ?? 'N/A',
                $nextTask->created_at->diffForHumans(),
            ]]
        );

        // Show description if available
        if ($nextTask->description) {
            $this->newLine();
            $this->line('Description:');
            $this->line($nextTask->description);
        }

        // Show notes if any
        if ($nextTask->notes()->count() > 0) {
            $this->newLine();
            $this->line('Notes:');
            foreach ($nextTask->notes->take(3) as $note) {
                $this->line("  • {$note->content}");
            }
            if ($nextTask->notes()->count() > 3) {
                $this->line("  ... and " . ($nextTask->notes()->count() - 3) . " more");
            }
        }

        // Show other pending tasks count
        $pendingCount = Task::pending()->count();
        if ($pendingCount > 1) {
            $this->newLine();
            $this->info("({$pendingCount} pending tasks total)");
        }

        $this->newLine();

        // Auto-start or prompt
        if ($this->option('start')) {
            $this->call('task:start', ['--id' => $nextTask->id]);
        } else {
            if ($this->confirm('Start this task now?', true)) {
                $this->call('task:start', ['--id' => $nextTask->id]);
            } else {
                $this->info("To start this task later, run: php artisan task:start --id={$nextTask->id}");
            }
        }

        return self::SUCCESS;
    }
}
