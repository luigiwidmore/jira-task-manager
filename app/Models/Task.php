<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Task extends Model
{
    use HasFactory;
    protected $fillable = [
        'title',
        'description',
        'status',
        'priority',
        'module',
        'git_branch',
        'git_files',
        'external_provider',
        'external_id',
        'is_synced',
        'last_synced_at',
        'created_by',
        'focused_at',
        'completed_at',
    ];

    protected $casts = [
        'git_files' => 'array',
        'is_synced' => 'boolean',
        'last_synced_at' => 'datetime',
        'focused_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    /**
     * Relationships
     */
    public function notes(): HasMany
    {
        return $this->hasMany(TaskNote::class);
    }

    /**
     * Scopes
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeInProgress($query)
    {
        return $query->where('status', 'in_progress');
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function scopeUnsynced($query)
    {
        return $query->where('is_synced', false);
    }

    public function scopeFocused($query)
    {
        return $query->whereNotNull('focused_at')->orderBy('focused_at', 'desc');
    }

    public function scopePrioritized($query)
    {
        return $query->orderByRaw("CASE priority
            WHEN 'urgent' THEN 1
            WHEN 'high' THEN 2
            WHEN 'medium' THEN 3
            ELSE 4 END");
    }

    /**
     * Helper Methods
     */
    public function generateBranchName(): string
    {
        return 'task/' . Str::slug($this->title);
    }

    public function markAsInProgress(): void
    {
        $this->update([
            'status' => 'in_progress',
            'focused_at' => now(),
        ]);
    }

    public function markAsCompleted(): void
    {
        $this->update([
            'status' => 'completed',
            'completed_at' => now(),
        ]);
    }

    public function markAsFocused(): void
    {
        // Unfocus all other tasks
        static::query()->update(['focused_at' => null]);

        // Focus this task
        $this->update(['focused_at' => now()]);
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isInProgress(): bool
    {
        return $this->status === 'in_progress';
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    public function isSynced(): bool
    {
        return $this->is_synced;
    }

    public function addNote(string $content, string $source = 'user'): TaskNote
    {
        return $this->notes()->create([
            'content' => $content,
            'source' => $source,
        ]);
    }

    /**
     * Accessors
     */
    public function getStatusLabelAttribute(): string
    {
        return match($this->status) {
            'pending' => '⏳ Pending',
            'in_progress' => '🔄 In Progress',
            'completed' => '✅ Completed',
            'cancelled' => '❌ Cancelled',
            default => $this->status,
        };
    }

    public function getPriorityLabelAttribute(): string
    {
        return match($this->priority) {
            'urgent' => '🔴 Urgent',
            'high' => '🟠 High',
            'medium' => '🟡 Medium',
            'low' => '🟢 Low',
            default => $this->priority,
        };
    }
}
