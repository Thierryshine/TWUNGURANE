<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration: Création de la table contributions
 * TWUNGURANE - Contributions des membres (épargne, pénalités, etc.)
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('contributions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')->constrained('groups')->onDelete('cascade');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->decimal('montant', 15, 2)->comment('Montant en FBU');
            $table->enum('type', ['epargne', 'penalite', 'pret', 'remboursement', 'retrait'])->default('epargne');
            $table->enum('moyen_paiement', ['lumicash', 'ecocash', 'mpesa', 'especes', 'virement'])->default('especes');
            $table->date('date_contribution');
            $table->string('reference_externe', 100)->nullable()->comment('Référence Mobile Money');
            $table->text('notes')->nullable();
            $table->enum('statut', ['en_attente', 'confirme', 'annule'])->default('confirme');
            $table->foreignId('enregistre_par')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();
            $table->softDeletes();

            // Index
            $table->index('group_id');
            $table->index('user_id');
            $table->index('date_contribution');
            $table->index('type');
            $table->index('statut');
            $table->index('reference_externe');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('contributions');
    }
};
