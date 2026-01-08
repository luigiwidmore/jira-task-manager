<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description')->nullable();
            $table->enum('status', ['pending', 'in_progress', 'completed', 'cancelled'])->default('pending');
            $table->enum('priority', ['low', 'medium', 'high', 'urgent'])->default('medium');
            $table->string('module')->nullable();

            // Git integration
            $table->string('git_branch')->nullable();
            $table->json('git_files')->nullable();

            // External sync (Jira/GitHub)
            $table->string('external_provider')->nullable(); // 'jira' or 'github'
            $table->string('external_id')->nullable();
            $table->boolean('is_synced')->default(false);
            $table->timestamp('last_synced_at')->nullable();

            // Context tracking
            $table->string('created_by')->default('claude'); // 'claude' or 'user'
            $table->timestamp('focused_at')->nullable();

            $table->timestamps();
            $table->timestamp('completed_at')->nullable();

            // Indexes
            $table->index('status');
            $table->index(['external_provider', 'external_id']);
            $table->index('is_synced');
        });

        Schema::create('task_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained()->cascadeOnDelete();
            $table->text('content');
            $table->string('source')->default('user'); // 'user' or 'claude'
            $table->timestamps();

            $table->index('task_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('task_notes');
        Schema::dropIfExists('tasks');
    }
};
