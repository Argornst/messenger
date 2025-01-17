<?php

namespace RTippin\Messenger\Tests\Http;

use Illuminate\Database\Eloquent\Factories\Sequence;
use RTippin\Messenger\Broadcasting\ReactionAddedBroadcast;
use RTippin\Messenger\Broadcasting\ReactionRemovedBroadcast;
use RTippin\Messenger\Contracts\MessengerProvider;
use RTippin\Messenger\Events\ReactionAddedEvent;
use RTippin\Messenger\Events\ReactionRemovedEvent;
use RTippin\Messenger\Models\Message;
use RTippin\Messenger\Models\MessageReaction;
use RTippin\Messenger\Models\Thread;
use RTippin\Messenger\Tests\FeatureTestCase;

class MessageReactionTest extends FeatureTestCase
{
    private Thread $private;
    private Message $message;
    private MessengerProvider $tippin;
    private MessengerProvider $doe;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tippin = $this->userTippin();
        $this->doe = $this->userDoe();
        $this->private = $this->createPrivateThread($this->tippin, $this->doe);
        $this->message = $this->createMessage($this->private, $this->tippin);
    }

    /** @test */
    public function non_participant_is_forbidden_to_react()
    {
        $this->actingAs($this->createJaneSmith());

        $this->postJson(route('api.messenger.threads.messages.reactions.store', [
            'thread' => $this->private->id,
            'message' => $this->message->id,
        ]), [
            'reaction' => ':joy:',
        ])
            ->assertForbidden();
    }

    /** @test */
    public function non_participant_is_forbidden_to_view_reacts()
    {
        $this->actingAs($this->createJaneSmith());

        $this->getJson(route('api.messenger.threads.messages.reactions.index', [
            'thread' => $this->private->id,
            'message' => $this->message->id,
        ]))
            ->assertForbidden();
    }

    /** @test */
    public function user_can_react_to_message()
    {
        $this->actingAs($this->tippin);

        $this->expectsEvents([
            ReactionAddedBroadcast::class,
            ReactionAddedEvent::class,
        ]);

        $this->postJson(route('api.messenger.threads.messages.reactions.store', [
            'thread' => $this->private->id,
            'message' => $this->message->id,
        ]), [
            'reaction' => ':joy:',
        ])
            ->assertSuccessful()
            ->assertJson([
                'message_id' => $this->message->id,
                'reaction' => ':joy:',
                'owner' => [
                    'name' => 'Richard Tippin',
                    'provider_id' => $this->tippin->getKey(),
                ],
            ]);
    }

    /** @test */
    public function participant_can_view_reacts()
    {
        MessageReaction::factory()
            ->for($this->message)
            ->for($this->tippin, 'owner')
            ->state(new Sequence(
                ['reaction' => ':one:'],
                ['reaction' => ':two:'],
                ['reaction' => ':three:'],
                ['reaction' => ':four:'],
                ['reaction' => ':five:'],
            ))
            ->count(5)
            ->create();
        MessageReaction::factory()
            ->for($this->message)
            ->for($this->doe, 'owner')
            ->state(new Sequence(
                ['reaction' => ':one:'],
                ['reaction' => ':two:'],
                ['reaction' => ':three:'],
                ['reaction' => ':four:'],
                ['reaction' => ':five:'],
                ['reaction' => ':six:'],
            ))
            ->count(6)
            ->create();

        $this->actingAs($this->tippin);

        $this->getJson(route('api.messenger.threads.messages.reactions.index', [
            'thread' => $this->private->id,
            'message' => $this->message->id,
        ]))
            ->assertSuccessful()
            ->assertJsonCount(6, 'data')
            ->assertJsonStructure([
                'data' => [
                    ':one:',
                    ':two:',
                    ':three:',
                    ':four:',
                    ':five:',
                    ':six:',
                ],
            ])
            ->assertJson([
                'meta' => [
                    'total' => 11,
                    'total_unique' => 6,
                ],
            ]);
    }

    /** @test */
    public function user_can_remove_own_reaction()
    {
        $reaction = MessageReaction::factory()
            ->for($this->message)
            ->for($this->tippin, 'owner')
            ->create();
        $this->actingAs($this->tippin);

        $this->expectsEvents([
            ReactionRemovedBroadcast::class,
            ReactionRemovedEvent::class,
        ]);

        $this->deleteJson(route('api.messenger.threads.messages.reactions.destroy', [
            'thread' => $this->private->id,
            'message' => $this->message->id,
            'reaction' => $reaction->id,
        ]))
            ->assertSuccessful();
    }

    /** @test */
    public function user_forbidden_to_remove_unowned_reaction()
    {
        $reaction = MessageReaction::factory()
            ->for($this->message)
            ->for($this->doe, 'owner')
            ->create();
        $this->actingAs($this->tippin);

        $this->deleteJson(route('api.messenger.threads.messages.reactions.destroy', [
            'thread' => $this->private->id,
            'message' => $this->message->id,
            'reaction' => $reaction->id,
        ]))
            ->assertForbidden();
    }

    /** @test */
    public function user_can_remove_unowned_reaction_when_group_admin()
    {
        $group = $this->createGroupThread($this->tippin, $this->doe);
        $message = $this->createMessage($group, $this->tippin);
        $reaction = MessageReaction::factory()
            ->for($message)
            ->for($this->doe, 'owner')
            ->create();
        $this->actingAs($this->tippin);

        $this->expectsEvents([
            ReactionRemovedBroadcast::class,
            ReactionRemovedEvent::class,
        ]);

        $this->deleteJson(route('api.messenger.threads.messages.reactions.destroy', [
            'thread' => $group->id,
            'message' => $message->id,
            'reaction' => $reaction->id,
        ]))
            ->assertSuccessful();
    }

    /**
     * @test
     * @dataProvider passesEmojiValidation
     * @param $string
     */
    public function it_passes_validating_has_valid_emoji($string)
    {
        $this->actingAs($this->tippin);

        $this->postJson(route('api.messenger.threads.messages.reactions.store', [
            'thread' => $this->private->id,
            'message' => $this->message->id,
        ]), [
            'reaction' => $string,
        ])
            ->assertSuccessful();
    }

    /**
     * @test
     * @dataProvider failsEmojiValidation
     * @param $string
     */
    public function it_fails_validating_has_valid_emoji($string)
    {
        $this->actingAs($this->tippin);

        $this->postJson(route('api.messenger.threads.messages.reactions.store', [
            'thread' => $this->private->id,
            'message' => $this->message->id,
        ]), [
            'reaction' => $string,
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('reaction');
    }

    public function passesEmojiValidation(): array
    {
        return [
            'Basic emoji shortcode' => [':poop:'],
            'Basic emoji utf8' => ['💩'],
            'Basic unicode emoji (:x:)' => ["\xE2\x9D\x8C"],
            'Basic ascii emoji' => [':)'],
            'Emoji found within string' => ['I tried to break :poop:'],
            'Emoji found within string after failed emoji' => ['I tried to break :unknown: :poop:'],
            'Multiple emojis it will pick first' => ['💩 :poop: 😁'],
        ];
    }

    public function failsEmojiValidation(): array
    {
        return [
            'Unknown emoji shortcode' => [':unknown:'],
            'String with no emojis' => ['I have no emojis'],
            'Invalid if shortcode spaced' => [': poop :'],
            'Cannot be empty' => [''],
            'Cannot be null' => [null],
            'Cannot be array' => [[0, 1]],
            'Cannot be integer' => [1],
            'Cannot be boolean' => [false],
        ];
    }
}
