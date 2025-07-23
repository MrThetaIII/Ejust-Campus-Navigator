<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('markers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->decimal('latitude', 10, 8);
            $table->decimal('longitude', 11, 8);
            $table->string('icon')->default('default');
            $table->timestamps();
        });

        Schema::create('roads', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->json('path'); // Array of lat/lng points
            $table->integer('width')->default(5);
            $table->string('color')->default('#0066cc');
            $table->timestamps();
        });

        Schema::create('campus_boundaries', function (Blueprint $table) {
            $table->id();
            $table->decimal('north', 10, 8);
            $table->decimal('south', 10, 8);
            $table->decimal('east', 11, 8);
            $table->decimal('west', 11, 8);
            $table->timestamps();
        });

        Schema::create('overlays', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('image_path');
            $table->json('corners'); // NW, NE, SE, SW lat/lng
            $table->decimal('opacity', 3, 2)->default(0.7);
            $table->boolean('active')->default(true);
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('overlays');
        Schema::dropIfExists('campus_boundaries');
        Schema::dropIfExists('roads');
        Schema::dropIfExists('markers');
    }
};
