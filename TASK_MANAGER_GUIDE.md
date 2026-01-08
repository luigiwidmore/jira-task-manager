# Task Manager + GitHub Integration Guide

A productivity system for managing tasks locally with git integration and GitHub Issues sync.

## 🚀 Quick Start

### Create Your First Task

```bash
# Quick capture (great for use by Claude Code)
php artisan task:capture "Implement user authentication" --priority=high --module=auth

# Or use the interactive start command
php artisan task:start "Add dark mode toggle"
```

### Work on a Task

```bash
# Start a task (creates git branch automatically)
php artisan task:start --id=1

# Your git branch is now: task/implement-user-authentication
# Make your changes...

# Add notes during work
php artisan task:append-note 1 "Using Laravel Sanctum for tokens"

# Complete when done (commits and merges automatically)
php artisan task:complete
```

### What's Next?

```bash
# See what to work on next (smart prioritization)
php artisan task:next

# View all tasks
php artisan task:list

# Get a summary (useful for Claude Code context)
php artisan task:summary
```

## 📋 Complete Command Reference

### Task Management

| Command | Description | Example |
|---------|-------------|---------|
| `task:start` | Start new or resume existing task | `php artisan task:start "Fix bug"` |
| `task:complete` | Complete task with git commit | `php artisan task:complete` |
| `task:next` | Show next recommended task | `php artisan task:next --start` |
| `task:list` | List all tasks with filters | `php artisan task:list --status=pending` |
| `task:capture` | Quick task capture | `php artisan task:capture "Title" --priority=high` |
| `task:append-note` | Add note to task | `php artisan task:append-note 5 "Note text"` |
| `task:summary` | Brief summary (JSON support) | `php artisan task:summary --json` |

### GitHub Integration

| Command | Description | Example |
|---------|-------------|---------|
| `task:push-github` | Push task to GitHub issue | `php artisan task:push-github 5` |
| `task:pull-github` | Pull GitHub issues to tasks | `php artisan task:pull-github` |
| `task:sync-github` | Bidirectional sync | `php artisan task:sync-github` |

## 🔄 GitHub Workflow

### Initial Setup

1. Make sure your project has a GitHub remote:
```bash
git remote add origin git@github.com:username/repo.git
```

2. Verify gh CLI is authenticated:
```bash
gh auth status
```

### Push Tasks to GitHub

```bash
# Push a single task
php artisan task:push-github 5

# Push all unsynced tasks
php artisan task:push-github --all

# Specify repository if auto-detection fails
php artisan task:push-github 5 --repo=username/repo
```

**What happens:**
- Creates GitHub issue with task details
- Adds labels based on priority and module
- Links task to issue (stores issue number)
- Marks task as synced

### Pull Issues from GitHub

```bash
# Pull recent issues
php artisan task:pull-github

# Pull only open issues
php artisan task:pull-github --state=open

# Limit number of issues
php artisan task:pull-github --limit=10
```

**What happens:**
- Fetches issues from GitHub
- Creates local tasks for new issues
- Updates existing tasks if issue changed
- Syncs issue state (open/closed) with task status

### Bidirectional Sync

```bash
# Full sync (pull then push)
php artisan task:sync-github

# Only pull from GitHub
php artisan task:sync-github --pull

# Only push to GitHub
php artisan task:sync-github --push
```

## 🌟 Workflow Examples

### Example 1: Solo Developer

```bash
# Morning: Check what to work on
php artisan task:next

# Start task (creates branch: task/add-oauth-support)
php artisan task:start --id=3

# Work with Claude Code...
# Make changes, test, iterate

# Complete (commits to branch, merges to main)
php artisan task:complete

# Push completed task to GitHub for tracking
php artisan task:push-github 3

# Repeat
php artisan task:next
```

### Example 2: Team Collaboration

```bash
# Pull issues created by teammates
php artisan task:pull-github

# See what's in your queue
php artisan task:list

# Start working on a synced issue
php artisan task:start --id=8

# Work on it...

# Complete and push back to GitHub
php artisan task:complete
php artisan task:push-github 8
# GitHub issue automatically updated
```

### Example 3: Brainstorming with Claude Code

In Claude Code CLI:

```
You: "I want to add a notification system to the app"

Claude: [Helps you brainstorm the feature]

You: "Create tasks for these"

Claude: [Uses task:capture multiple times]
$ php artisan task:capture "Design notification schema" --priority=high --module=notifications
$ php artisan task:capture "Create notification service" --priority=high --module=notifications
$ php artisan task:capture "Add notification UI component" --priority=medium --module=ui
$ php artisan task:capture "Write notification tests" --priority=medium --module=notifications

You: "Push all these to GitHub"

Claude:
$ php artisan task:push-github --all
# Creates 4 GitHub issues with appropriate labels
```

## 📊 Task Filtering & Organization

```bash
# Filter by status
php artisan task:list --status=pending
php artisan task:list --status=in_progress
php artisan task:list --status=completed

# Filter by priority
php artisan task:list --priority=urgent
php artisan task:list --priority=high

# Filter by module
php artisan task:list --module=auth
php artisan task:list --module=ui

# Show only synced/unsynced
php artisan task:list --synced
php artisan task:list --unsynced

# Combine filters
php artisan task:list --status=pending --priority=high --limit=5
```

## 🏷️ Labels & Organization

### Priority Labels (GitHub)
- `priority: urgent` - Urgent priority tasks
- `priority: high` - High priority
- `priority: medium` - Medium priority (default)
- `priority: low` - Low priority

### Module Labels
Automatically added based on `--module` flag:
- `module: auth` - Authentication related
- `module: ui` - User interface
- `module: api` - API endpoints
- `module: database` - Database changes
- etc.

### Sync Label
- `synced-from-local` - Indicates issue was created from local task

## 🎯 Best Practices

### 1. Start Every Work Session Right

```bash
# Check summary
php artisan task:summary

# Pull any new issues from team
php artisan task:pull-github

# See what's next
php artisan task:next --start
```

### 2. Keep Tasks Focused

- One feature per task
- Use notes to track progress
- Complete tasks before switching

### 3. Sync Regularly

```bash
# End of day: push your work
php artisan task:push-github --all

# Start of day: pull team updates
php artisan task:pull-github
```

### 4. Use Git Integration

- Let the system manage branches (automatic with `task:start`)
- Clean commit history (one task = one branch = one merge)
- Never manually commit on task branches (use `task:complete`)

### 5. Leverage Claude Code

Create tasks during brainstorming:
```bash
# Claude can execute:
php artisan task:capture "Title" --priority=high --source=claude

# Later you start them:
php artisan task:start --id=X
```

## 🔧 Advanced Usage

### Custom Repository

```bash
# If working on multiple repos
php artisan task:push-github 5 --repo=myorg/other-repo
php artisan task:pull-github --repo=myorg/other-repo
```

### JSON Output (for scripts/AI)

```bash
# Get JSON summary
php artisan task:summary --json

# Use in scripts
TASK_JSON=$(php artisan task:summary --json)
echo $TASK_JSON | jq '.current_task.title'
```

### Task Lifecycle States

- **pending** - Created, not started
- **in_progress** - Currently working on (has focused_at timestamp)
- **completed** - Finished (has completed_at timestamp)
- **cancelled** - Abandoned

### Handling Uncommitted Changes

When starting a task with uncommitted changes:
```bash
php artisan task:start "New task"
# Prompts: "You have uncommitted changes. Stash them?"
# If yes: auto-stashes with message
# If no: cancels task start
```

## 🐛 Troubleshooting

### "gh CLI not available"

```bash
# Install GitHub CLI
brew install gh

# Authenticate
gh auth login
```

### "Could not detect repository"

```bash
# Check git remote
git remote -v

# Add if missing
git remote add origin git@github.com:username/repo.git

# Or specify manually
php artisan task:push-github 5 --repo=username/repo
```

### Task Already Synced

If you try to push an already-synced task:
- System warns you
- Asks if you want to update the GitHub issue
- Useful for syncing changes back

### Merge Conflicts

If `task:complete` fails to merge:
- System leaves you on the task branch
- Fix conflicts manually
- Run `task:complete` again

## 💡 Tips & Tricks

### Quick Workflow Aliases

Add to your shell profile:

```bash
alias tn='php artisan task:next'
alias ts='php artisan task:start'
alias tc='php artisan task:complete'
alias tl='php artisan task:list'
alias tsum='php artisan task:summary'
alias tpush='php artisan task:push-github'
alias tsync='php artisan task:sync-github'
```

### Integration with Claude Code

In `.claude/commands/`:
- `brainstorm-to-github.md` - Convert brainstorming to GitHub issues
- `task-to-github.md` - Push specific task to GitHub
- `github-sync.md` - Full sync workflow

### Morning Routine

```bash
#!/bin/bash
# morning.sh - Start your day right

echo "📅 Morning Task Briefing"
echo "========================"
echo ""

# Pull updates
echo "📥 Pulling from GitHub..."
php artisan task:pull-github --state=open

echo ""

# Show summary
php artisan task:summary

echo ""

# What's next?
php artisan task:next
```

### End of Day Sync

```bash
#!/bin/bash
# eod-sync.sh - End of day sync

echo "🌙 End of Day Sync"
echo "=================="
echo ""

# Show what you did today
php artisan task:list --status=completed | grep "$(date +%Y-%m-%d)"

echo ""

# Push everything to GitHub
echo "📤 Pushing to GitHub..."
php artisan task:push-github --all

echo ""
echo "✓ All synced! Good work today!"
```

## 🎓 Learning Resources

### Understanding the Database

Tasks are stored in SQLite (`database/database.sqlite`):

- **tasks** table - Main task data
- **task_notes** table - Notes attached to tasks

### Key Fields

- `git_branch` - Auto-generated branch name
- `external_provider` - 'github' when synced
- `external_id` - GitHub issue number
- `is_synced` - Sync status flag
- `focused_at` - When task was last focused
- `completed_at` - When task was completed

### Extending the System

See `/app/Models/Task.php` for model methods
See `/app/Services/GitService.php` for git operations
See `/app/Console/Commands/Task/` for command implementations

## 📚 Next Steps

1. **Try it**: Start with a simple task
2. **Sync it**: Push to GitHub
3. **Build habit**: Use daily
4. **Customize**: Add your own commands
5. **Share**: Sync with your team

---

**Built with Laravel 12, Pest Testing, and GitHub CLI**
