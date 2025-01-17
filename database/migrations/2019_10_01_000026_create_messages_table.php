<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use RTippin\Messenger\Support\Helpers;

class CreateMessagesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('messages', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('thread_id');
            Helpers::SchemaMorphType('owner', $table);
            $table->integer('type')->index();
            $table->text('body');
            $table->uuid('reply_to_id')->nullable()->index();
            $table->boolean('edited')->default(0);
            $table->boolean('reacted')->default(0);
            $table->timestamps(6);
            $table->softDeletes();
            $table->index('created_at');
            $table->foreign('thread_id')
                ->references('id')
                ->on('threads')
                ->onDelete('cascade')
                ->onUpdate('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('messages');
    }
}
