<?php

namespace RTippin\Messenger\Contracts;

use Illuminate\Database\Eloquent\Model;

/**
 * App\Contracts\MessengerProvider.
 *
 * @mixin Model|\Eloquent
 */
interface MessengerProvider
{
    /**
     * Format and return your provider name here.
     * ex: $this->first . ' ' . $this->last.
     *
     * @return string
     */
    public function name();

    /**
     * The column name your providers avatar is stored in the database as.
     *
     * @return string
     */
    public function getAvatarColumn();

    /**
     * The column name your provider has in the database that we will use to
     * show last active, and touch / update timestamp when using our online
     * heartbeat. This should be a timestamp column.
     *
     * @return string
     */
    public function getLastActiveColumn(): string;

    /**
     * Get the route of the avatar for your provider. We will call this
     * from our resource classes using sm/md/lg .
     *
     * @param string $size
     * @param bool $api
     * @return string|null
     */
    public function getAvatarRoute(string $size = 'sm', $api = false);

    /**
     * If your provider has a route/slug for a profile page,
     * return that route here.
     *
     * @return string|null
     */
    public function getRoute();

    /**
     * Returns online status of your provider.
     * 0 - offline, 1 - online, 2 - away.
     *
     * @return int
     */
    public function onlineStatus();

    /**
     * Verbose meaning of the online status number.
     *
     * @return string
     */
    public function onlineStatusVerbose();
}
