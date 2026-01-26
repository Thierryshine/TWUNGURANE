<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration: Création de la table transactions
 * TWUNGURANE - Historique immuable de toutes les transactions
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 100)->unique()->comment('Référence unique de transaction');
            $table->foreignId('user_id')->constrained('users')->onDelete('restrict');
            $table->foreignId('group_id')->nullable()->constrained('groups')->onDelete('set null');
            $table->decimal('montant', 15, 2)->comment('Montant en FBU');
            $table->enum('type', [
                'contribution',
                'pret',
                'remboursement',
                'penalite',
                'retrait',
                'transfert',
                'frais'
            ]);
            $table->enum('source', [
                'lumicash',
                'ecocash',
                'mpesa',
                'especes',
                'virement',
                'interne'
            ])->default('interne');
            $table->json('metadata')->nullable()->comment('Données supplémentaires (référence externe, etc.)');
            $table->enum('statut', ['en_attente', 'traite', 'echec', 'annule'])->default('traite');
            $table->text('description')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamps();

            // Index pour recherche et reporting
            $table->index('reference');
            $table->index('user_id');
            $table->index('group_id');
            $table->index('type');
            $table->index('statut');
            $table->index('created_at');
            $table->index(['user_id', 'created_at']);
            $table->index(['group_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
