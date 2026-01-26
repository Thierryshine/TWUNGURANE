<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration: Création de la table group_members
 * TWUNGURANE - Membres des groupes d'épargne
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('group_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')->constrained('groups')->onDelete('cascade');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->enum('role_dans_groupe', ['admin', 'tresorier', 'membre'])->default('membre');
            $table->date('date_adhesion');
            $table->enum('statut', ['actif', 'suspendu', 'retire'])->default('actif');
            $table->decimal('total_contributions', 15, 2)->default(0)->comment('Total des contributions en FBU');
            $table->integer('nombre_contributions')->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // Index et contraintes uniques
            $table->unique(['group_id', 'user_id'], 'unique_group_user');
            $table->index('group_id');
            $table->index('user_id');
            $table->index('statut');
            $table->index('role_dans_groupe');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('group_members');
    }
};
