<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('email');
            $table->dateTime('slot_start');
            $table->dateTime('slot_end');
            $table->timestamps();

            $table->index(['service_id', 'slot_start', 'slot_end']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bookings');
    }
};
