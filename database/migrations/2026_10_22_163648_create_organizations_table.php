<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organizations', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->string('slug', 100)->unique();
            $table->string('phone', 30)->nullable();
            $table->string('address')->nullable();

            // Subscription
            $table->string('plan', 20)->default('trial'); // trial | monthly | annual
            $table->decimal('plan_price', 10, 2)->default(0);
            $table->date('trial_ends_at')->nullable();
            $table->date('subscribed_until')->nullable();
            $table->string('status', 20)->default('trial'); // trial | active | suspended

            $table->text('notes')->nullable(); // admin notes

            $table->timestamps();
        });

        // Link users to an organization
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('organization_id')->nullable()->after('id')->constrained()->nullOnDelete();
            $table->boolean('is_org_owner')->default(false)->after('organization_id');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['organization_id']);
            $table->dropColumn(['organization_id', 'is_org_owner']);
        });

        Schema::dropIfExists('organizations');
    }
};
