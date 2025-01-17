<?php

namespace RTippin\Messenger\Repositories;

use Illuminate\Database\Eloquent\Collection;
use RTippin\Messenger\Contracts\MessengerProvider;
use RTippin\Messenger\Messenger;
use RTippin\Messenger\Models\Thread;
use RTippin\Messenger\Support\Helpers;

class PrivateThreadRepository
{
    /**
     * @var Messenger
     */
    protected Messenger $messenger;

    /**
     * PrivateThreadRepository constructor.
     *
     * @param Messenger $messenger
     */
    public function __construct(Messenger $messenger)
    {
        $this->messenger = $messenger;
    }

    /**
     * @param MessengerProvider|null $recipient
     * @return Thread|null
     */
    public function getProviderPrivateThreadWithRecipient(MessengerProvider $recipient = null): ?Thread
    {
        if ($this->messenger->isValidMessengerProvider($recipient)) {
            return Thread::hasProvider('participants', $this->messenger->getProvider())
                ->private()
                ->hasProvider('participants', $recipient)
                ->first();
        }

        return null;
    }

    /**
     * @return Collection
     */
    public function getProviderPrivateThreadsIndex(): Collection
    {
        return Thread::hasProvider('participants', $this->messenger->getProvider())
            ->private()
            ->latest('updated_at')
            ->with([
                'participants.owner',
                'recentMessage.owner',
                'activeCall.participants.owner',
            ])
            ->limit($this->messenger->getThreadsIndexCount())
            ->get();
    }

    /**
     * @param Thread $thread
     * @return Collection
     */
    public function getProviderPrivateThreadsPage(Thread $thread): Collection
    {
        return Thread::hasProvider('participants', $this->messenger->getProvider())
            ->private()
            ->latest('updated_at')
            ->with([
                'participants.owner',
                'recentMessage.owner',
                'activeCall.participants.owner',
            ])
            ->where('threads.updated_at', '<=', Helpers::PrecisionTime($thread->updated_at))
            ->where('threads.id', '!=', $thread->id)
            ->limit($this->messenger->getThreadsPageCount())
            ->get();
    }

    /**
     * @return Thread|null
     */
    public function getProviderOldestPrivateThread(): ?Thread
    {
        return Thread::hasProvider('participants', $this->messenger->getProvider())
            ->private()
            ->oldest('updated_at')
            ->first();
    }
}
