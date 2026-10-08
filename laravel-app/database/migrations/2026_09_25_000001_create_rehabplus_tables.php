<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('patients')) {
            Schema::create('patients', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id')->nullable();
                $table->string('name', 100);
                $table->string('condition', 150);
                $table->text('medical_summary')->nullable();
                $table->text('therapy_plan')->nullable();
                $table->string('avatar')->nullable();
                $table->string('assigned_to')->nullable();
                $table->timestamps();
            });
        }

        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'role')) {
                $table->enum('role', ['superadmin', 'manager', 'staff', 'patient', 'therapist'])->default('staff');
            }
            if (! Schema::hasColumn('users', 'avatar')) {
                $table->string('avatar')->nullable();
            }
            if (! Schema::hasColumn('users', 'is_active')) {
                $table->boolean('is_active')->default(true);
            }
        });

        if (! Schema::hasTable('super_admins')) {
            Schema::create('super_admins', function (Blueprint $table) {
                $table->id();
                $table->string('email')->unique();
                $table->string('password');
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('exercise_records')) {
            Schema::create('exercise_records', function (Blueprint $table) {
                $table->id();
                $table->foreignId('patient_id')->constrained('patients')->cascadeOnDelete();
                $table->string('exercise_name', 150);
                $table->integer('sets_prescribed');
                $table->integer('sets_completed');
                $table->tinyInteger('pain_level');
                $table->text('notes')->nullable();
                $table->dateTime('recorded_at');
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('appointments')) {
            Schema::create('appointments', function (Blueprint $table) {
                $table->id();
                $table->string('patient', 100);
                $table->string('therapist', 100);
                $table->string('patient_condition', 150);
                $table->string('contact', 50);
                $table->date('date');
                $table->time('time');
                $table->string('session', 100);
                $table->text('notes')->nullable();
                $table->string('status', 30)->default('Upcoming');
                $table->dateTime('completed_at')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('appointments');
        Schema::dropIfExists('exercise_records');
        Schema::dropIfExists('super_admins');
        Schema::dropIfExists('patients');

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['role', 'avatar', 'is_active']);
        });
    }
};
