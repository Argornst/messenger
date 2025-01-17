<?php

namespace RTippin\Messenger\Tests\Commands;

use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use RTippin\Messenger\Contracts\MessengerProvider;
use RTippin\Messenger\Facades\Messenger;
use RTippin\Messenger\Jobs\EndCalls;
use RTippin\Messenger\Models\Thread;
use RTippin\Messenger\Tests\FeatureTestCase;

class CallsDownCommandTest extends FeatureTestCase
{
    private MessengerProvider $tippin;
    private Thread $group;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tippin = $this->userTippin();
        $this->group = $this->createGroupThread($this->tippin);
        Bus::fake();
    }

    /** @test */
    public function it_sets_cache_lockout_key_and_doesnt_dispatch_job_when_no_calls()
    {
        $this->artisan('messenger:calls:down')
            ->expectsOutput('No active calls to end found.')
            ->expectsOutput('Call system is now down for 30 minutes.')
            ->assertExitCode(0);

        $this->assertTrue(Cache::has('messenger:calls:down'));
        Bus::assertNotDispatched(EndCalls::class);
    }

    /** @test */
    public function it_does_nothing_if_calling_disabled()
    {
        Messenger::setCalling(false);

        $this->artisan('messenger:calls:down')
            ->expectsOutput('Call system currently disabled.')
            ->assertExitCode(0);

        $this->assertFalse(Cache::has('messenger:calls:down'));
        Bus::assertNotDispatched(EndCalls::class);
    }

    /** @test */
    public function it_does_nothing_if_calling_already_down()
    {
        Messenger::disableCallsTemporarily(1);

        $this->artisan('messenger:calls:down')
            ->expectsOutput('Call system is already shutdown.')
            ->assertExitCode(0);

        $this->assertTrue(Cache::has('messenger:calls:down'));
        Bus::assertNotDispatched(EndCalls::class);
    }

    /** @test */
    public function it_dispatches_job_and_sets_cache_lockout()
    {
        $this->createCall($this->group, $this->tippin);

        $this->artisan('messenger:calls:down')
            ->expectsOutput('1 active calls found. End calls dispatched!')
            ->expectsOutput('Call system is now down for 30 minutes.')
            ->assertExitCode(0);

        Bus::assertDispatched(EndCalls::class);
    }

    /** @test */
    public function it_dispatches_job_now()
    {
        $this->createCall($this->group, $this->tippin);

        $this->artisan('messenger:calls:down', [
            '--now' => true,
        ])
            ->expectsOutput('1 active calls found. End calls completed!')
            ->expectsOutput('Call system is now down for 30 minutes.')
            ->assertExitCode(0);

        Bus::assertDispatched(EndCalls::class);
    }

    /** @test */
    public function it_can_set_cache_lockout_time()
    {
        $this->artisan('messenger:calls:down', [
            '--duration' => 60,
        ])
            ->expectsOutput('No active calls to end found.')
            ->expectsOutput('Call system is now down for 60 minutes.')
            ->assertExitCode(0);

        $this->assertTrue(Cache::has('messenger:calls:down'));
    }

    /** @test */
    public function it_finds_multiple_calls()
    {
        $this->createCall($this->group, $this->tippin);
        $this->createCall($this->group, $this->tippin);

        $this->artisan('messenger:calls:down')
            ->expectsOutput('2 active calls found. End calls dispatched!')
            ->expectsOutput('Call system is now down for 30 minutes.')
            ->assertExitCode(0);

        $this->assertTrue(Cache::has('messenger:calls:down'));
        Bus::assertDispatched(EndCalls::class);
    }
}
