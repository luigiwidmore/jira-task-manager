<?php

use App\Models\Task;
use App\Models\TaskNote;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    // Database is automatically migrated and reset due to RefreshDatabase trait
});

it('can capture a task', function () {
    $this->artisan('task:capture', [
        'title' => 'Test Task',
        '--priority' => 'high',
        '--module' => 'testing',
    ])->assertSuccessful();

    expect(Task::count())->toBe(1);

    $task = Task::first();
    expect($task->title)->toBe('Test Task');
    expect($task->priority)->toBe('high');
    expect($task->module)->toBe('testing');
    expect($task->status)->toBe('pending');
});

it('can list tasks', function () {
    // Create some tasks
    Task::factory()->create(['title' => 'Task 1', 'status' => 'pending']);
    Task::factory()->create(['title' => 'Task 2', 'status' => 'in_progress']);
    Task::factory()->create(['title' => 'Task 3', 'status' => 'completed']);

    $this->artisan('task:list')->assertSuccessful();

    expect(Task::count())->toBe(3);
});

it('can filter tasks by status', function () {
    Task::factory()->create(['status' => 'pending']);
    Task::factory()->create(['status' => 'pending']);
    Task::factory()->create(['status' => 'in_progress']);

    $this->artisan('task:list', ['--status' => 'pending'])
        ->assertSuccessful();

    expect(Task::pending()->count())->toBe(2);
});

it('can add notes to tasks', function () {
    $task = Task::factory()->create();

    $this->artisan('task:append-note', [
        'id' => $task->id,
        'note' => 'This is a test note',
    ])->assertSuccessful();

    $task->refresh();
    expect($task->notes()->count())->toBe(1);
    expect($task->notes->first()->content)->toBe('This is a test note');
});

it('can show next recommended task', function () {
    // Create tasks with different priorities
    Task::factory()->create(['priority' => 'low', 'status' => 'pending']);
    Task::factory()->create(['priority' => 'urgent', 'status' => 'pending']);
    Task::factory()->create(['priority' => 'medium', 'status' => 'pending']);

    $this->artisan('task:next')
        ->expectsConfirmation('Start this task now?', 'no')
        ->assertSuccessful();

    // The urgent task should be recommended
    $nextTask = Task::pending()->prioritized()->first();
    expect($nextTask->priority)->toBe('urgent');
});

it('can show task summary', function () {
    Task::factory()->create(['status' => 'pending']);
    Task::factory()->create(['status' => 'in_progress']);
    Task::factory()->create(['status' => 'completed', 'completed_at' => now()]);

    $this->artisan('task:summary')->assertSuccessful();

    expect(Task::pending()->count())->toBe(1);
    expect(Task::inProgress()->count())->toBe(1);
    expect(Task::completed()->count())->toBe(1);
});

it('can mark task as completed', function () {
    $task = Task::factory()->create([
        'status' => 'in_progress',
        'focused_at' => now(),
    ]);

    expect($task->isCompleted())->toBeFalse();

    $task->markAsCompleted();

    expect($task->isCompleted())->toBeTrue();
    expect($task->completed_at)->not->toBeNull();
});

it('can generate branch name from task title', function () {
    $task = Task::factory()->create(['title' => 'Fix Authentication Bug']);

    $branchName = $task->generateBranchName();

    expect($branchName)->toBe('task/fix-authentication-bug');
});

it('can add notes with different sources', function () {
    $task = Task::factory()->create();

    $task->addNote('User note', 'user');
    $task->addNote('Claude note', 'claude');

    expect($task->notes()->count())->toBe(2);
    expect($task->notes()->fromUser()->count())->toBe(1);
    expect($task->notes()->fromClaude()->count())->toBe(1);
});

it('shows correct status labels', function () {
    $task = Task::factory()->create(['status' => 'pending']);
    expect($task->status_label)->toBe('⏳ Pending');

    $task->update(['status' => 'in_progress']);
    expect($task->status_label)->toBe('🔄 In Progress');

    $task->update(['status' => 'completed']);
    expect($task->status_label)->toBe('✅ Completed');
});

it('shows correct priority labels', function () {
    $task = Task::factory()->create(['priority' => 'low']);
    expect($task->priority_label)->toBe('🟢 Low');

    $task->update(['priority' => 'medium']);
    expect($task->priority_label)->toBe('🟡 Medium');

    $task->update(['priority' => 'high']);
    expect($task->priority_label)->toBe('🟠 High');

    $task->update(['priority' => 'urgent']);
    expect($task->priority_label)->toBe('🔴 Urgent');
});
