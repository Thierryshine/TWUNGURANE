<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration: Création de la table users
 * TWUNGURANE - Gestion des utilisateurs
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('nom', 100);
            $table->string('prenom', 100);
            $table->string('telephone', 20)->unique()->comment('Format: +257 XX XX XX XX');
            $table->string('email', 255)->unique()->nullable();
            $table->string('password');
            $table->enum('role', ['admin', 'tresorier', 'membre'])->default('membre');
            $table->enum('statut', ['actif', 'suspendu', 'inactif'])->default('actif');
            $table->timestamp('email_verified_at')->nullable();
            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes();

            // Index pour améliorer les performances
            $table->index('telephone');
            $table->index('email');
            $table->index('role');
            $table->index('statut');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
