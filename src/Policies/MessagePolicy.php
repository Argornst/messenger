<?php

namespace RTippin\Messenger\Policies;

use Illuminate\Auth\Access\HandlesAuthorization;
use RTippin\Messenger\Messenger;
use RTippin\Messenger\Models\Message;
use RTippin\Messenger\Models\Thread;

class MessagePolicy
{
    use HandlesAuthorization;

    /**
     * @var Messenger
     */
    private Messenger $messenger;

    /**
     * MessagePolicy constructor.
     *
     * @param Messenger $messenger
     */
    public function __construct(Messenger $messenger)
    {
        $this->messenger = $messenger;
    }

    /**
     * Determine whether the provider can view any models.
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
     * Determine whether the provider can view the model.
     *
     * @param $user
     * @param Thread $thread
     * @return mixed
     */
    public function view($user, Thread $thread)
    {
        return $thread->hasCurrentProvider()
            ? $this->allow()
            : $this->deny('Not authorized to view message.');
    }

    /**
     * Determine whether the provider can view the model edits.
     *
     * @param $user
     * @param Message $message
     * @param Thread $thread
     * @return mixed
     */
    public function viewEdits($user, Message $message, Thread $thread)
    {
        return $thread->hasCurrentProvider()
        && $this->messenger->isMessageEditsEnabled()
        && $this->messenger->isMessageEditsViewEnabled()
        && $message->isEdited()
            ? $this->allow()
            : $this->deny('Not authorized to view edits for that message.');
    }

    /**
     * Determine whether the provider can create models.
     *
     * @param $user
     * @param Thread $thread
     * @return mixed
     */
    public function create($user, Thread $thread)
    {
        return $thread->canMessage()
            ? $this->allow()
            : $this->deny('Not authorized to send messages.');
    }

    /**
     * Determine whether the provider can create models.
     *
     * @param $user
     * @param Thread $thread
     * @return mixed
     */
    public function createDocument($user, Thread $thread)
    {
        return $this->messenger->isMessageDocumentUploadEnabled() && $thread->canMessage()
            ? $this->allow()
            : $this->deny('Not authorized to upload a document.');
    }

    /**
     * Determine whether the provider can create models.
     *
     * @param $user
     * @param Thread $thread
     * @return mixed
     */
    public function createAudio($user, Thread $thread)
    {
        return $this->messenger->isMessageAudioUploadEnabled() && $thread->canMessage()
            ? $this->allow()
            : $this->deny('Not authorized to upload audio.');
    }

    /**
     * Determine whether the provider can create models.
     *
     * @param $user
     * @param Thread $thread
     * @return mixed
     */
    public function createImage($user, Thread $thread)
    {
        return $this->messenger->isMessageImageUploadEnabled() && $thread->canMessage()
            ? $this->allow()
            : $this->deny('Not authorized to upload image.');
    }

    /**
     * Determine whether the provider can update the model.
     *
     * @param $user
     * @param Message $message
     * @param Thread $thread
     * @return mixed
     */
    public function update($user, Message $message, Thread $thread)
    {
        return ! $thread->isLocked()
        && $message->isText()
        && $this->messenger->isMessageEditsEnabled()
        && (string) $this->messenger->getProviderId() === (string) $message->owner_id
        && $this->messenger->getProviderClass() === $message->owner_type
            ? $this->allow()
            : $this->deny('Not authorized to update message.');
    }

    /**
     * Determine whether the provider can delete the model.
     *
     * @param $user
     * @param Message $message
     * @param Thread $thread
     * @return mixed
     */
    public function delete($user, Message $message, Thread $thread)
    {
        return ! $thread->isLocked()
        && ! $message->isSystemMessage()
        && (((string) $this->messenger->getProviderId() === (string) $message->owner_id
                && $this->messenger->getProviderClass() === $message->owner_type)
            || $thread->isAdmin())
            ? $this->allow()
            : $this->deny('Not authorized to remove message.');
    }
}
