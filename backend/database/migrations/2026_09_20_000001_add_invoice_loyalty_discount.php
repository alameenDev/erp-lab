<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void { Schema::table('invoices',function(Blueprint $t){$t->unsignedBigInteger('loyalty_discount')->default(0);$t->unsignedInteger('loyalty_points_spent')->default(0);$t->string('loyalty_reward_key')->nullable();}); }
    public function down(): void { Schema::table('invoices',fn(Blueprint $t)=>$t->dropColumn(['loyalty_discount','loyalty_points_spent','loyalty_reward_key'])); }
};
