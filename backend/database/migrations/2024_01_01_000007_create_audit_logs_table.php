<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration: Création de la table audit_logs
 * TWUNGURANE - Journalisation des actions sensibles
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('set null');
            $table->string('action', 100)->comment('create, update, delete, approve, reject, etc.');
            $table->string('model', 100)->comment('Nom du modèle (User, Group, Loan, etc.)');
            $table->unsignedBigInteger('model_id')->nullable()->comment('ID du modèle affecté');
            $table->json('old_values')->nullable()->comment('Valeurs avant modification');
            $table->json('new_values')->nullable()->comment('Valeurs après modification');
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->text('description')->nullable();
            $table->timestamps();

            // Index pour recherche et audit
            $table->index('user_id');
            $table->index('action');
            $table->index('model');
            $table->index(['model', 'model_id']);
            $table->index('created_at');
            $table->index(['user_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
