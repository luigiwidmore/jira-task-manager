<?php

namespace App\Console\Commands\Task;

use App\Models\Task;
use Illuminate\Console\Command;

class ListCommand extends Command
{
    protected $signature = 'task:list
                            {--status= : Filter by status (pending, in_progress, completed)}
                            {--priority= : Filter by priority (low, medium, high, urgent)}
                            {--module= : Filter by module}
                            {--synced : Show only synced tasks}
                            {--unsynced : Show only unsynced tasks}
                            {--limit=20 : Limit number of results}';

    protected $description = 'List tasks with optional filters';

    public function handle(): int
    {
        $query = Task::query()->orderBy('created_at', 'desc');

        // Apply filters
        if ($status = $this->option('status')) {
            $query->where('status', $status);
        }

        if ($priority = $this->option('priority')) {
            $query->where('priority', $priority);
        }

        if ($module = $this->option('module')) {
            $query->where('module', $module);
        }

        if ($this->option('synced')) {
            $query->where('is_synced', true);
        }

        if ($this->option('unsynced')) {
            $query->where('is_synced', false);
        }

        $tasks = $query->limit((int) $this->option('limit'))->get();

        if ($tasks->isEmpty()) {
            $this->info('No tasks found matching the criteria.');
            return self::SUCCESS;
        }

        // Prepare table data
        $rows = $tasks->map(function (Task $task) {
            return [
                $task->id,
                $this->truncate($task->title, 40),
                $task->status_label,
                $task->priority_label,
                $task->module ?? 'N/A',
                $task->is_synced ? '✓' : '✗',
                $task->created_at->format('Y-m-d H:i'),
            ];
        })->toArray();

        $this->table(
            ['ID', 'Title', 'Status', 'Priority', 'Module', 'Synced', 'Created'],
            $rows
        );

        // Show summary
        $this->newLine();
        $this->line('Summary:');
        $this->line("  Pending: " . Task::pending()->count());
        $this->line("  In Progress: " . Task::inProgress()->count());
        $this->line("  Completed: " . Task::completed()->count());
        $this->line("  Unsynced: " . Task::unsynced()->count());

        return self::SUCCESS;
    }

    protected function truncate(string $text, int $length): string
    {
        if (strlen($text) <= $length) {
            return $text;
        }

        return substr($text, 0, $length - 3) . '...';
    }
}
