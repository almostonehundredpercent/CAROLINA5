<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
return new class extends Migration { public function up(): void { Schema::create('business_contacts', function (Blueprint $table) { $table->id(); $table->string('phone')->nullable(); $table->string('email')->nullable(); $table->string('address')->nullable(); $table->string('hours')->nullable(); $table->string('facebook_url')->nullable(); $table->timestamps(); }); DB::table('business_contacts')->insert(['address'=>'Tabaco City, Albay','facebook_url'=>'https://www.facebook.com/profile.php?id=61556306344437','created_at'=>now(),'updated_at'=>now()]); } public function down(): void { Schema::dropIfExists('business_contacts'); } };
