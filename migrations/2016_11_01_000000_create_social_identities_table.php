<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateSocialIdentitiesTable extends Migration
{
    /**
     * Run the migration.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('social_identities', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('user_id')->unsigned()->index()->nullable();
            $table->string('provider')->nullable();
            $table->string('reference')->nullable();
            $table->text('access_token')->nullable();
            $table->string('expires_at')->nullable();
            $table->string('refresh_token')->nullable();
            $table->string('confirm_token')->nullable();
            $table->dateTime('confirm_until')->nullable();
            $table->dateTime('confirmed_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migration.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('social_identities');
    }
}
