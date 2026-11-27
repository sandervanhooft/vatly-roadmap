<?php

use App\Models\Item;
use App\Models\User;
use App\Models\Vote;
use App\Models\Comment;
use App\Models\UserSocial;
use function Pest\Laravel\artisan;
use Xetaio\Mentions\Models\Mention;

// mailinator.com is on the package's bundled blocklist, so these tests need no network.

test('dry run lists disposable-domain accounts with their content and deletes nothing', function () {
    $bot = User::factory()->unverified()->create(['email' => 'xk2j9qpz@mailinator.com']);
    $human = User::factory()->create(['email' => 'jane@gmail.com']);
    Vote::factory()->create(['user_id' => $bot->id, 'model_type' => Item::class, 'model_id' => Item::factory()->create()->id]);

    artisan('users:purge-disposable')
        ->expectsOutputToContain('xk2j9qpz@mailinator.com')
        ->doesntExpectOutputToContain('jane@gmail.com')
        ->expectsOutputToContain('Matched 1 account(s) on disposable email domains (0 verified, 1 unverified).')
        ->expectsOutputToContain('1 of them created content: 0 item(s), 0 comment(s), 1 vote(s).')
        ->expectsOutputToContain('Dry run: nothing was changed.')
        ->assertSuccessful();

    expect(User::find($bot->id))->not->toBeNull()
        ->and(User::find($human->id))->not->toBeNull()
        ->and(Vote::count())->toBe(1);
});

test('force deletes disposable-domain accounts and their content without touching other accounts', function () {
    $bot = User::factory()->create(['email' => 'qq81zz@mailinator.com']);
    $staff = User::factory()->admin()->create(['email' => 'staff@mailinator.com']);
    $human = User::factory()->create(['email' => 'jane@gmail.com']);
    UserSocial::factory()->create(['user_id' => $bot->id]);

    $botItem = Item::factory()->create(['user_id' => $bot->id]);
    $humanItem = Item::factory()->create(['user_id' => $human->id]);
    $humanItem->toggleUpvote($human);
    $humanItem->toggleUpvote($bot);
    $bot->assignedItems()->attach($humanItem);

    // A human reply under the bot's comment: comments.parent_id has a foreign key with no cascade.
    $botComment = Comment::factory()->create(['user_id' => $bot->id, 'item_id' => $humanItem->id]);
    $reply = Comment::factory()->create(['user_id' => $human->id, 'item_id' => $humanItem->id, 'parent_id' => $botComment->id]);
    $reply->toggleUpvote($human);
    $humanComment = Comment::factory()->create(['user_id' => $human->id, 'item_id' => $humanItem->id]);
    Mention::create(['model_type' => Comment::class, 'model_id' => $humanComment->id, 'recipient_type' => User::class, 'recipient_id' => $bot->id]);

    expect($humanItem->fresh()->total_votes)->toBe(2);

    artisan('users:purge-disposable --force')
        ->expectsOutputToContain('Deleted 1 account(s).')
        ->assertSuccessful();

    expect(User::find($bot->id))->toBeNull()
        ->and(User::find($staff->id))->not->toBeNull()
        ->and(User::find($human->id))->not->toBeNull()
        ->and(UserSocial::where('user_id', $bot->id)->exists())->toBeFalse()
        ->and($botItem->fresh()->user_id)->toBeNull()
        ->and($humanItem->fresh()->total_votes)->toBe(1)
        ->and($humanItem->assignedUsers()->exists())->toBeFalse()
        ->and(Comment::whereIn('id', [$botComment->id, $reply->id])->exists())->toBeFalse()
        ->and(Vote::where('model_type', Comment::class)->exists())->toBeFalse()
        ->and(Comment::find($humanComment->id))->not->toBeNull()
        ->and(Mention::count())->toBe(0);

    artisan('users:purge-disposable --force')
        ->expectsOutputToContain('Matched 0 account(s)')
        ->expectsOutputToContain('Deleted 0 account(s).')
        ->assertSuccessful();
});

test('unverified-only skips verified disposable-domain accounts', function () {
    User::factory()->create(['email' => 'kept@mailinator.com']);
    User::factory()->unverified()->create(['email' => 'bot@mailinator.com']);

    artisan('users:purge-disposable --unverified-only')
        ->expectsOutputToContain('bot@mailinator.com')
        ->doesntExpectOutputToContain('kept@mailinator.com')
        ->assertSuccessful();
});
