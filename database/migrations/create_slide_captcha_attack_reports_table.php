<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('slide_captcha_attack_reports', function (Blueprint $table) {
            $table->id();
            $table->string('report_id', 64)->unique();
            $table->timestamp('occurred_at')->index();
            $table->string('action', 32)->index();
            $table->string('severity', 32)->index();
            $table->string('endpoint', 32)->nullable()->index();
            $table->string('reason', 64)->index();
            $table->string('ip', 64)->nullable()->index();
            $table->string('identity_hash', 64)->nullable()->index();
            $table->string('user_agent_hash', 64)->nullable();
            $table->string('session_hash', 64)->nullable();
            $table->unsignedInteger('retry_after')->nullable();
            $table->unsignedInteger('score')->nullable();
            $table->string('limit_key', 191)->nullable();
            $table->string('request_method', 16)->nullable();
            $table->string('request_path', 191)->nullable();
            $table->json('details')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('slide_captcha_attack_reports');
    }
};
