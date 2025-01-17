<?php

namespace RTippin\Messenger\Policies;

use Illuminate\Auth\Access\HandlesAuthorization;
use RTippin\Messenger\Messenger;
use RTippin\Messenger\Models\Message;
use RTippin\Messenger\Models\MessageReaction;
use RTippin\Messenger\Models\Thread;

class MessageReactionPolicy
{
    use HandlesAuthorization;

    /**
     * @var Messenger
     */
    private Messenger $messenger;

    /**
     * MessageReactionPolicy constructor.
     *
     * @param Messenger $messenger
     */
    public function __construct(Messenger $messenger)
    {
        $this->messenger = $messenger;
    }

    /**
     * Determine whether the provider can view reactions.
     *
     * @param $user
     * @param Thread $thread
     * @return mixed
     */
    public function viewAny($user, Thread $thread)
    {
        return $thread->hasCurrentProvider()
            ? $this->allow()
            : $this->deny('Not authorized to view messages.');
    }

    /**
     * Determine whether the provider can create a reaction.
     *
     * @param $user
     * @param Message $message
     * @param Thread $thread
     * @return mixed
     */
    public function create($user, Thread $thread, Message $message)
    {
        return $this->messenger->isMessageReactionsEnabled()
        && ! $message->isSystemMessage()
        && $thread->canMessage()
            ? $this->allow()
            : $this->deny('Not authorized to react to message.');
    }

    /**
     * Determine whether the provider can delete the reaction.
     *
     * @param $user
     * @param MessageReaction $reaction
     * @param Thread $thread
     * @return mixed
     */
    public function delete($user, MessageReaction $reaction, Thread $thread)
    {
        return ! $thread->isLocked()
        && (((string) $this->messenger->getProviderId() === (string) $reaction->owner_id
                && $this->messenger->getProviderClass() === $reaction->owner_type)
            || $thread->isAdmin())
            ? $this->allow()
            : $this->deny('Not authorized to remove reaction.');
    }
}
