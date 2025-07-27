<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        // Add image and location support to hops
        Schema::table('hops', function (Blueprint $table) {
            $table->string('image_path')->nullable()->after('icon');
            $table->string('location_code')->default('main-campus')->after('image_path');
            $table->index('location_code');
        });

        // Add location support to other tables
        Schema::table('road_connections', function (Blueprint $table) {
            $table->string('location_code')->default('main-campus')->after('distance');
            $table->index('location_code');
        });

        Schema::table('campus_boundaries', function (Blueprint $table) {
            $table->string('location_code')->default('main-campus')->after('west');
            $table->index('location_code');
        });

        Schema::table('overlays', function (Blueprint $table) {
            $table->string('location_code')->default('main-campus')->after('active');
            $table->index('location_code');
        });

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
    }

    public function down()
    {
        Schema::dropIfExists('locations');

        Schema::table('overlays', function (Blueprint $table) {
            $table->dropIndex(['location_code']);
            $table->dropColumn('location_code');
        });

        Schema::table('campus_boundaries', function (Blueprint $table) {
            $table->dropIndex(['location_code']);
            $table->dropColumn('location_code');
        });

        Schema::table('road_connections', function (Blueprint $table) {
            $table->dropIndex(['location_code']);
            $table->dropColumn('location_code');
        });

        Schema::table('hops', function (Blueprint $table) {
            $table->dropIndex(['location_code']);
            $table->dropColumn(['image_path', 'location_code']);
        });
    }
};
