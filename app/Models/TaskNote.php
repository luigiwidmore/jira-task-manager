<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TaskNote extends Model
{
    protected $fillable = [
        'task_id',
        'content',
        'source',
    ];

    /**
     * Relationships
     */
    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    /**
     * Scopes
     */
    public function scopeFromClaude($query)
    {
        return $query->where('source', 'claude');
    }

    public function scopeFromUser($query)
    {
        return $query->where('source', 'user');
    }
}
