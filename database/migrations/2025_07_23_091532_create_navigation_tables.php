<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        // Create locations table
        Schema::create('locations', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        // Seed default locations
        DB::table('locations')->insert([
            ['code' => 'main-campus', 'name' => 'Main Campus', 'description' => 'Primary campus location'],
            ['code' => 'northern-dorms', 'name' => 'Northern Dormitories', 'description' => 'Northern residential area'],
            ['code' => 'southern-dorms', 'name' => 'Southern Dormitories', 'description' => 'Southern residential area'],
            ['code' => 'western-dorms', 'name' => 'Western Dormitories', 'description' => 'Western residential area'],
        ]);

        Schema::create('campus_boundaries', function (Blueprint $table) {
            $table->id();
            $table->decimal('north', 10, 8);
            $table->decimal('south', 10, 8);
            $table->decimal('east', 11, 8);
            $table->decimal('west', 11, 8);
            $table->string('location_code')->default('main-campus');
            $table->index('location_code');
            $table->foreign('location_code')->references('code')->on('locations')->onDelete('cascade');
            $table->timestamps();
        });

        Schema::create('overlays', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('image_path');
            $table->json('corners'); // NW, NE, SE, SW lat/lng
            $table->decimal('opacity', 3, 2)->default(0.7);
            $table->boolean('active')->default(true);
            $table->string('location_code')->default('main-campus');
            $table->index('location_code');
            $table->foreign('location_code')->references('code')->on('locations')->onDelete('cascade');
            $table->timestamps();
        });

        Schema::create('hops', function (Blueprint $table) {
            $table->id();
            $table->string('type')->default('hop'); // 'marker' or 'hop'
            $table->string('name')->nullable();
            $table->text('description')->nullable();
            $table->decimal('latitude', 10, 8);
            $table->decimal('longitude', 11, 8);
            $table->string('icon')->default('default');
            $table->string('image_path')->nullable();
            $table->string('location_code')->default('main-campus');
            $table->index('location_code');
            $table->foreign('location_code')->references('code')->on('locations')->onDelete('cascade');
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
            $table->string('location_code')->default('main-campus');
            $table->index('location_code');
            $table->foreign('location_code')->references('code')->on('locations')->onDelete('cascade');
            $table->foreign('hop_from_id')->references('id')->on('hops')->onDelete('cascade');
            $table->foreign('hop_to_id')->references('id')->on('hops')->onDelete('cascade');
            $table->unique(['hop_from_id', 'hop_to_id']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('overlays');
        Schema::dropIfExists('campus_boundaries');
        Schema::dropIfExists('road_connections');
        Schema::dropIfExists('hops');
        Schema::dropIfExists('locations');
    }
};
