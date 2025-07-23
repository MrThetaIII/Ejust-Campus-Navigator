<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        // Create hops table (includes both markers and connection points)
        Schema::create('hops', function (Blueprint $table) {
            $table->id();
            $table->string('type')->default('hop'); // 'marker' or 'hop'
            $table->string('name')->nullable();
            $table->text('description')->nullable();
            $table->decimal('latitude', 10, 8);
            $table->decimal('longitude', 11, 8);
            $table->string('icon')->default('default');
            $table->timestamps();
        });

        // Create road connections between hops
        Schema::create('road_connections', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('hop_from_id');
            $table->unsignedBigInteger('hop_to_id');
            $table->string('name')->nullable();
            $table->integer('width')->default(5);
            $table->string('color')->default('#0066cc');
            $table->decimal('distance', 10, 2)->nullable(); // in meters
            $table->timestamps();

            $table->foreign('hop_from_id')->references('id')->on('hops')->onDelete('cascade');
            $table->foreign('hop_to_id')->references('id')->on('hops')->onDelete('cascade');
            $table->unique(['hop_from_id', 'hop_to_id']);
        });

        // Drop old tables
        Schema::dropIfExists('markers');
        Schema::dropIfExists('roads');
    }

    public function down()
    {
        Schema::dropIfExists('road_connections');
        Schema::dropIfExists('hops');
    }
};
