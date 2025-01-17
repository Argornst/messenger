<?php

namespace RTippin\Messenger\Tests\Http;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use RTippin\Messenger\Broadcasting\NewMessageBroadcast;
use RTippin\Messenger\Contracts\MessengerProvider;
use RTippin\Messenger\Events\NewMessageEvent;
use RTippin\Messenger\Facades\Messenger;
use RTippin\Messenger\Models\Thread;
use RTippin\Messenger\Tests\FeatureTestCase;

class AudioMessageTest extends FeatureTestCase
{
    private Thread $private;
    private MessengerProvider $tippin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tippin = $this->userTippin();
        $this->private = $this->createPrivateThread($this->tippin, $this->userDoe());
        Storage::fake(Messenger::getThreadStorage('disk'));
    }

    /** @test */
    public function user_can_send_audio_message()
    {
        $this->actingAs($this->tippin);

        $this->expectsEvents([
            NewMessageBroadcast::class,
            NewMessageEvent::class,
        ]);

        $this->postJson(route('api.messenger.threads.audio.store', [
            'thread' => $this->private->id,
        ]), [
            'audio' => UploadedFile::fake()->create('test.mp3', 500, 'audio/mpeg'),
            'temporary_id' => '123-456-789',
        ])
            ->assertSuccessful()
            ->assertJson([
                'thread_id' => $this->private->id,
                'temporary_id' => '123-456-789',
                'type' => 3,
                'type_verbose' => 'AUDIO_MESSAGE',
                'owner' => [
                    'provider_id' => $this->tippin->getKey(),
                    'provider_alias' => 'user',
                    'name' => 'Richard Tippin',
                ],
            ]);
    }

    /** @test */
    public function audio_message_mime_types_can_be_overwritten()
    {
        Messenger::setMessageAudioMimeTypes('3gpp');
        $this->actingAs($this->tippin);

        $this->expectsEvents([
            NewMessageBroadcast::class,
            NewMessageEvent::class,
        ]);

        $this->postJson(route('api.messenger.threads.audio.store', [
            'thread' => $this->private->id,
        ]), [
            'audio' => UploadedFile::fake()->create('test.3gp', 500, 'audio/3gpp'),
            'temporary_id' => '123-456-789',
        ])
            ->assertSuccessful();
    }

    /** @test */
    public function audio_message_size_limit_can_be_overwritten()
    {
        Messenger::setMessageAudioSizeLimit(20480);
        $this->actingAs($this->tippin);

        $this->expectsEvents([
            NewMessageBroadcast::class,
            NewMessageEvent::class,
        ]);

        $this->postJson(route('api.messenger.threads.audio.store', [
            'thread' => $this->private->id,
        ]), [
            'audio' => UploadedFile::fake()->create('test.mp3', 18000, 'audio/mpeg'),
            'temporary_id' => '123-456-789',
        ])
            ->assertSuccessful();
    }

    /** @test */
    public function user_forbidden_to_send_audio_message_when_disabled_from_config()
    {
        Messenger::setMessageAudioUpload(false);
        $this->actingAs($this->tippin);

        $this->postJson(route('api.messenger.threads.audio.store', [
            'thread' => $this->private->id,
        ]), [
            'audio' => UploadedFile::fake()->create('test.mp3', 500, 'audio/mpeg'),
            'temporary_id' => '123-456-789',
        ])
            ->assertForbidden();
    }

    /**
     * @test
     * @dataProvider audioPassesValidation
     * @param $audioValue
     */
    public function send_audio_message_passes_audio_validation($audioValue)
    {
        $this->actingAs($this->tippin);

        $this->postJson(route('api.messenger.threads.audio.store', [
            'thread' => $this->private->id,
        ]), [
            'audio' => $audioValue,
            'temporary_id' => '123-456-789',
        ])
            ->assertSuccessful();
    }

    /**
     * @test
     * @dataProvider audioFailsValidation
     * @param $audioValue
     */
    public function send_audio_message_fails_audio_validation($audioValue)
    {
        $this->actingAs($this->tippin);

        $this->postJson(route('api.messenger.threads.audio.store', [
            'thread' => $this->private->id,
        ]), [
            'audio' => $audioValue,
            'temporary_id' => '123-456-789',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('audio');
    }

    public function audioFailsValidation(): array
    {
        return [
            'Audio cannot be empty' => [''],
            'Audio cannot be integer' => [5],
            'Audio cannot be null' => [null],
            'Audio cannot be an array' => [[1, 2]],
            'Audio cannot be a movie' => [UploadedFile::fake()->create('movie.mov', 500, 'video/quicktime')],
            'Audio cannot be a mp4' => [UploadedFile::fake()->create('movie.mp4', 500, 'video/mp4')],
            'Audio cannot be an image' => [UploadedFile::fake()->image('image.jpg')],
            'Audio must be 10240 kb or less' => [UploadedFile::fake()->create('test.mp3', 10241, 'audio/mpeg')],
        ];
    }

    public function audioPassesValidation(): array
    {
        return [
            'Audio can be 10240 kb max limit' => [UploadedFile::fake()->create('test.mp3', 10240, 'audio/mpeg')],
            'Audio can be aac' => [UploadedFile::fake()->create('test.aac', 500, 'audio/aac')],
            'Audio can be mp3' => [UploadedFile::fake()->create('test.mp3', 500, 'audio/mpeg')],
            'Audio can be oga' => [UploadedFile::fake()->create('test.oga', 500, 'audio/ogg')],
            'Audio can be wav' => [UploadedFile::fake()->create('test.wav', 500, 'audio/wav')],
            'Audio can be weba' => [UploadedFile::fake()->create('test.weba', 500, 'audio/webm')],
            'Audio can be webm' => [UploadedFile::fake()->create('test.webm', 500, 'audio/webm')],
        ];
    }
}
