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

class DocumentMessageTest extends FeatureTestCase
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
    public function user_can_send_document_message()
    {
        $this->actingAs($this->tippin);

        $this->expectsEvents([
            NewMessageBroadcast::class,
            NewMessageEvent::class,
        ]);

        $this->postJson(route('api.messenger.threads.documents.store', [
            'thread' => $this->private->id,
        ]), [
            'document' => UploadedFile::fake()->create('test.pdf', 500, 'application/pdf'),
            'temporary_id' => '123-456-789',
        ])
            ->assertSuccessful()
            ->assertJson([
                'thread_id' => $this->private->id,
                'temporary_id' => '123-456-789',
                'type' => 2,
                'type_verbose' => 'DOCUMENT_MESSAGE',
                'owner' => [
                    'provider_id' => $this->tippin->getKey(),
                    'provider_alias' => 'user',
                    'name' => 'Richard Tippin',
                ],
            ]);
    }

    /** @test */
    public function document_message_mime_types_can_be_overwritten()
    {
        Messenger::setMessageDocumentMimeTypes('mp3');
        $this->actingAs($this->tippin);

        $this->expectsEvents([
            NewMessageBroadcast::class,
            NewMessageEvent::class,
        ]);

        $this->postJson(route('api.messenger.threads.documents.store', [
            'thread' => $this->private->id,
        ]), [
            'document' => UploadedFile::fake()->create('song.mp3', 500, 'audio/mpeg'),
            'temporary_id' => '123-456-789',
        ])
            ->assertSuccessful();
    }

    /** @test */
    public function document_message_size_limit_can_be_overwritten()
    {
        Messenger::setMessageDocumentSizeLimit(20480);
        $this->actingAs($this->tippin);

        $this->expectsEvents([
            NewMessageBroadcast::class,
            NewMessageEvent::class,
        ]);

        $this->postJson(route('api.messenger.threads.documents.store', [
            'thread' => $this->private->id,
        ]), [
            'document' => UploadedFile::fake()->create('test.pdf', 18000, 'application/pdf'),
            'temporary_id' => '123-456-789',
        ])
            ->assertSuccessful();
    }

    /** @test */
    public function user_forbidden_to_send_document_message_when_disabled_from_config()
    {
        Messenger::setMessageDocumentUpload(false);
        $this->actingAs($this->tippin);

        $this->postJson(route('api.messenger.threads.documents.store', [
            'thread' => $this->private->id,
        ]), [
            'document' => UploadedFile::fake()->create('test.pdf', 500, 'application/pdf'),
            'temporary_id' => '123-456-789',
        ])
            ->assertForbidden();
    }

    /**
     * @test
     * @dataProvider documentPassesValidation
     * @param $documentValue
     */
    public function send_document_message_passes_document_validation($documentValue)
    {
        $this->actingAs($this->tippin);

        $this->postJson(route('api.messenger.threads.documents.store', [
            'thread' => $this->private->id,
        ]), [
            'document' => $documentValue,
            'temporary_id' => '123-456-789',
        ])
            ->assertSuccessful();
    }

    /**
     * @test
     * @dataProvider documentFailsValidation
     * @param $documentValue
     */
    public function send_document_message_fails_document_validation($documentValue)
    {
        $this->actingAs($this->tippin);

        $this->postJson(route('api.messenger.threads.documents.store', [
            'thread' => $this->private->id,
        ]), [
            'document' => $documentValue,
            'temporary_id' => '123-456-789',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('document');
    }

    public function documentFailsValidation(): array
    {
        return [
            'Document cannot be empty' => [''],
            'Document cannot be integer' => [5],
            'Document cannot be null' => [null],
            'Document cannot be an array' => [[1, 2]],
            'Document cannot be a movie' => [UploadedFile::fake()->create('movie.mov', 500, 'video/quicktime')],
            'Document cannot be a mp4' => [UploadedFile::fake()->create('movie.mp4', 500, 'video/mp4')],
            'Document cannot be a mp3' => [UploadedFile::fake()->create('song.mp3', 500, 'audio/mpeg')],
            'Document cannot be a wav song' => [UploadedFile::fake()->create('song.wav', 500, 'audio/wav')],
            'Document must be 10240 kb or less' => [UploadedFile::fake()->create('test.pdf', 10241, 'application/pdf')],
            'Document cannot be an image' => [UploadedFile::fake()->image('picture.png')],
        ];
    }

    public function documentPassesValidation(): array
    {
        return [
            'Document can be pdf file' => [UploadedFile::fake()->create('test.pdf', 500, 'application/pdf')],
            'Document can be text file' => [UploadedFile::fake()->create('test.txt', 500, 'text/plain')],
            'Document can be doc file' => [UploadedFile::fake()->create('test.doc', 500, 'application/msword')],
            'Document can be docx file' => [UploadedFile::fake()->create('test.docx', 500, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document')],
            'Document can be ppt file' => [UploadedFile::fake()->create('test.ppt', 500, 'application/vnd.ms-powerpoint')],
            'Document can be pptx file' => [UploadedFile::fake()->create('test.pptx', 500, 'application/vnd.openxmlformats-officedocument.presentationml.presentation')],
            'Document can be xls file' => [UploadedFile::fake()->create('test.xls', 500, 'application/vnd.ms-excel')],
            'Document can be xlsx file' => [UploadedFile::fake()->create('test.xlsx', 500, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')],
            'Document can be rar file' => [UploadedFile::fake()->create('test.rar', 500, 'application/x-rar')],
            'Document can be zip file' => [UploadedFile::fake()->create('test.zip', 500, 'application/zip')],
            'Document can be 7z file' => [UploadedFile::fake()->create('test.7z', 500, 'application/x-7z-compressed')],
            'Document can be xml file' => [UploadedFile::fake()->create('test.xml', 500, 'text/xml')],
            'Document can be rtf file' => [UploadedFile::fake()->create('test.xml', 500, 'application/rtf')],
            'Document can be json file' => [UploadedFile::fake()->create('test.json', 500, 'application/json')],
            'Document can be csv file' => [UploadedFile::fake()->create('test.csv', 500, 'text/csv')],
            'Document can be 10240 kb max limit' => [UploadedFile::fake()->create('test.pdf', 10240, 'application/pdf')],
        ];
    }
}
