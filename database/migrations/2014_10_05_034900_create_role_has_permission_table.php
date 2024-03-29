<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('role_has_permission', function (Blueprint $table) {
            $table->integer('permission_id');
            $table->integer('role_id');
            $table->foreign('permission_id')->references('id_permission')->on('permission')->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreign('role_id')->references('id_detail_roles')->on('detail_role')->cascadeOnDelete()->cascadeOnUpdate();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('role_has_permission');
    }
};