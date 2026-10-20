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
        Schema::create('guests', function (Blueprint $table) {
            $table->id();
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('mobile')->nullable(); 
            $table->string('country', 2)->nullable(); 
            $table->enum('tag', ['regular', 'vip', 'blacklisted'])->default('regular');
            $table->integer('total_stays')->default(1);
            $table->string('notes')->nullable();
            $table->timestamps();
        });

        DB::statement('
            CREATE UNIQUE INDEX guests_lower_email_unique 
            ON guests (LOWER(email)) 
            WHERE email IS NOT NULL;
        ');

        DB::statement('
            CREATE UNIQUE INDEX guests_phone_unique 
            ON guests (phone) 
            WHERE phone IS NOT NULL;
        ');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('guests');
    }
};
