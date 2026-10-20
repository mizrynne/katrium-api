<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('units', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->foreignId('parent_id')->nullable()->constrained('units')->nullOnDelete();
            $table->foreignId('property_id')->after('id')->constrained('properties')->cascadeOnDelete();
            $table->string('beds24_room_id')->nullable()->index();
            $table->integer('qty')->default(1);
            $table->integer('capacity')->default(1);
            $table->integer('max_adults')->nullable();
            $table->integer('max_children')->nullable(); 
            $table->boolean('is_active')->default(true);
            $table->foreignId('partner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['is_active', 'property_id'], 'units_active_property_idx');
            $table->index('partner_id', 'units_partner_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('units');
    }
};
