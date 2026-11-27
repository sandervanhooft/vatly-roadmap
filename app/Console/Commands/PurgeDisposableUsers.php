<?php

namespace App\Console\Commands;

use Throwable;
use App\Models\User;
use App\Models\Vote;
use App\Enums\UserRole;
use App\Models\Comment;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Xetaio\Mentions\Models\Mention;
use Illuminate\Support\Facades\Validator;

class PurgeDisposableUsers extends Command
{
    protected $signature = 'users:purge-disposable
        {--unverified-only : Only match accounts that never verified their email address}
        {--force : Delete the matched accounts; without it this is a dry run}';

    protected $description = 'List, or with --force delete, user accounts registered with a disposable email domain';

    public function handle(): int
    {
        $users = $this->matchedUsers();

        $this->table(
            ['ID', 'Name', 'Email', 'Source', 'Verified', 'Created', 'Items', 'Comments', 'Votes'],
            $users->map(fn (User $user) => [
                $user->id,
                $user->name,
                $user->email,
                $user->userSocials->isEmpty() ? 'email' : 'sso:' . $user->userSocials->pluck('provider')->unique()->implode(','),
                $user->hasVerifiedEmail() ? 'yes' : 'no',
                $user->created_at?->toDateTimeString(),
                $user->items_count,
                $user->comments_count,
                $user->votes_count,
            ])
        );

        $verified = $users->filter(fn (User $user) => $user->hasVerifiedEmail())->count();
        $withContent = $users->filter(fn (User $user) => $user->items_count + $user->comments_count + $user->votes_count > 0);

        $this->line("Matched {$users->count()} account(s) on disposable email domains ({$verified} verified, " . ($users->count() - $verified) . ' unverified).');
        $this->line("{$withContent->count()} of them created content: {$users->sum('items_count')} item(s), {$users->sum('comments_count')} comment(s), {$users->sum('votes_count')} vote(s).");

        if ($withContent->isNotEmpty()) {
            $this->warn('Accounts with content: ' . $withContent->pluck('id')->implode(', '));
        }

        if (! $this->option('force')) {
            $this->info('Dry run: nothing was changed. Run again with --force to delete these accounts.');

            return self::SUCCESS;
        }

        $failed = 0;

        foreach ($users as $user) {
            try {
                $this->purge($user);
            } catch (Throwable $exception) {
                $failed++;
                $this->error("Could not delete user {$user->id} ({$user->email}): {$exception->getMessage()}");
            }
        }

        $this->info('Deleted ' . ($users->count() - $failed) . ' account(s).');

        return $failed ? self::FAILURE : self::SUCCESS;
    }

    /**
     * Non-staff users whose email fails the same `indisposable` rule that signup uses.
     */
    protected function matchedUsers(): Collection
    {
        return User::query()
            ->where(fn ($query) => $query->whereNull('role')->orWhere('role', UserRole::User->value))
            ->when($this->option('unverified-only'), fn ($query) => $query->whereNull('email_verified_at'))
            ->with('userSocials:id,user_id,provider')
            ->withCount(['items', 'comments', 'votes'])
            ->lazyById()
            ->filter(fn (User $user) => Validator::make(['email' => $user->email], ['email' => 'email'])->passes()
                && Validator::make(['email' => $user->email], ['email' => 'indisposable'])->fails())
            ->values()
            ->collect();
    }

    /**
     * Removes what UserObserver::deleting does not handle safely, then deletes the user through
     * Eloquent so the observer removes their votes, socials, and unlinks their items.
     */
    protected function purge(User $user): void
    {
        DB::transaction(function () use ($user) {
            $comment = (new Comment)->getMorphClass();

            // The user's comments and every reply beneath them, as CommentObserver would cascade.
            $commentIds = collect();
            $level = $user->comments()->pluck('id');
            while ($level->isNotEmpty()) {
                $commentIds = $commentIds->merge($level);
                $level = Comment::whereIn('parent_id', $level)->pluck('id')->diff($commentIds);
            }

            Vote::where('model_type', $comment)->whereIn('model_id', $commentIds)->delete();
            Mention::where('model_type', $comment)->whereIn('model_id', $commentIds)->delete();
            Comment::whereIn('id', $commentIds)->update(['parent_id' => null]);
            Comment::whereIn('id', $commentIds)->delete();

            // Deleted one by one so VoteObserver recalculates each item's total_votes.
            $user->votes()->get()->each(fn (Vote $vote) => $vote->delete());

            // Delete the mention rows themselves; UserObserver's mentions()->delete() would delete the comments mentioning the user.
            Mention::where('recipient_type', $user->getMorphClass())->where('recipient_id', $user->id)->delete();

            $user->assignedItems()->detach();
            $user->projects()->detach();
            $user->tokens()->delete();
            $user->notifications()->delete();

            $user->delete();
        });
    }
}
