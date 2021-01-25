<?php

namespace RTippin\Messenger\Tests\Http;

use RTippin\Messenger\Contracts\MessengerProvider;
use RTippin\Messenger\Models\Thread;
use RTippin\Messenger\Tests\FeatureTestCase;

class ThreadChannelTest extends FeatureTestCase
{
    private Thread $private;

    private MessengerProvider $tippin;

    private MessengerProvider $doe;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tippin = $this->userTippin();

        $this->doe = $this->userDoe();

        $this->private = $this->createPrivateThread($this->tippin, $this->doe);
    }

    protected function getEnvironmentSetUp($app): void
    {
        parent::getEnvironmentSetUp($app);

        $config = $app->get('config');

        // Need to set a driver other than null
        // for broadcast routes to be utilized
        $config->set('broadcasting.default', 'redis');
    }

    /** @test */
    public function guest_is_unauthorized()
    {
        $this->postJson('/api/broadcasting/auth', [
            'channel_name' => "presence-thread.{$this->private->id}",
        ])
            ->assertUnauthorized();
    }

    /** @test */
    public function missing_thread_forbidden()
    {
        $this->actingAs($this->tippin);

        $this->postJson('/api/broadcasting/auth', [
            'channel_name' => 'presence-thread.404',
        ])
            ->assertForbidden();
    }
}