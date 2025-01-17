<?php

namespace RTippin\Messenger\Http\Resources;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use RTippin\Messenger\Contracts\FriendDriver;
use RTippin\Messenger\Contracts\MessengerProvider;
use RTippin\Messenger\Facades\Messenger;
use RTippin\Messenger\Repositories\ThreadRepository;
use RTippin\Messenger\Support\Definitions;

/**
 * @property-read Model|MessengerProvider $provider
 */
class ProviderStatusResource extends JsonResource
{
    /**
     * @var Model|MessengerProvider
     */
    protected $provider;

    /**
     * @var bool
     */
    protected bool $addOptions;

    /**
     * ProviderStatusResource constructor.
     *
     * @param mixed $provider
     */
    public function __construct($provider)
    {
        parent::__construct($provider);

        $this->provider = $provider;
    }

    /**
     * Transform the resource into an array.
     *
     * @param  Request  $request
     * @return array
     * @noinspection PhpMissingParamTypeInspection
     */
    public function toArray($request): array
    {
        return [
            'provider' => (new ProviderResource($this->provider))->resolve(),
            'active_calls_count' => $this->activeCallsCount(),
            'online_status' => $this->provider->onlineStatus(),
            'online_status_verbose' => Definitions::OnlineStatus[$this->provider->onlineStatus()],
            'unread_threads_count' => $this->unreadThreadsCount(),
            'pending_friends_count' => $this->pendingFriendsCount(),
            'settings' => Messenger::getProviderMessenger($this->provider)->toArray(),
        ];
    }

    /**
     * @return int
     */
    private function unreadThreadsCount(): int
    {
        return app(ThreadRepository::class)
            ->getProviderUnreadThreadsBuilder()
            ->count();
    }

    /**
     * @return int
     */
    private function pendingFriendsCount(): int
    {
        return app(FriendDriver::class)
            ->getProviderPendingFriends()
            ->count();
    }

    /**
     * @return int
     */
    private function activeCallsCount(): int
    {
        return app(ThreadRepository::class)
                ->getProviderThreadsWithActiveCallsBuilder()
                ->count();
    }
}
