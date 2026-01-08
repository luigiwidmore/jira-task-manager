<?php

namespace App\Console\Commands\Task;

use App\Models\Task;
use Illuminate\Console\Command;

class AppendNoteCommand extends Command
{
    protected $signature = 'task:append-note
                            {id : The task ID}
                            {note : The note content}
                            {--source=user : Source of the note (user or claude)}';

    protected $description = 'Add a note to a task';

    public function handle(): int
    {
        $taskId = $this->argument('id');
        $task = Task::find($taskId);

        if (!$task) {
            $this->error("Task #{$taskId} not found");
            return self::FAILURE;
        }

        $note = $task->addNote($this->argument('note'), $this->option('source'));

        $this->info("Note added to task #{$task->id}");
        $this->line("  {$note->content}");

        return self::SUCCESS;
    }
}
