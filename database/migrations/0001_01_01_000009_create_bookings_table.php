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
        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('unit_id')->constrained()->cascadeOnDelete();
            $table->foreignId('guest_id')->nullable()->constrained()->nullOnDelete();
            $table->string('beds24_booking_id')->nullable()->unique();
            $table->string('api_reference')->nullable()->index(); 
            $table->string('ota_source')->default('direct'); 
            $table->string('sync_status')->default('pending');
            $table->timestamp('last_synced_at')->nullable()->index();
            $table->text('sync_error')->nullable();
            $table->jsonb('channel_data')->nullable();
            $table->string('channel_confirmation_code')->nullable()->index();
            $table->decimal('commission_amount', 10, 2)->default(0);
            $table->dateTime('check_in'); 
            $table->dateTime('check_out'); 
            $table->integer('num_adults')->default(1); 
            $table->integer('num_children')->default(0); 
            $table->enum('status', ['confirmed', 'pending', 'canceled', 'altered', 'noshow'])->default('confirmed');
            $table->tinyInteger('beds24_status')->nullable(); 
            $table->decimal('total_price', 10, 2);
            $table->decimal('amount_paid', 10, 2)->default(0); 
            $table->decimal('deposit', 10, 2)->default(0); 
            $table->timestamps();

            $table->index(['unit_id', 'check_in', 'check_out'], 'bookings_gantt_lookup_idx');
            $table->index(['status', 'sync_status'], 'bookings_status_sync_status_idx');
        });

        DB::statement(<<<'SQL'
            ALTER TABLE bookings
            ADD CONSTRAINT bookings_unit_id_time_overlap_excl
            EXCLUDE USING gist (
                unit_id WITH =,
                tsrange(check_in, check_out + INTERVAL '1 hour', '[)') WITH &&
            )
            WHERE (status NOT IN ('canceled', 'noshow'))
        SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bookings');
    }
};
