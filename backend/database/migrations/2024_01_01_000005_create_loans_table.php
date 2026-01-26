<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration: Création de la table loans
 * TWUNGURANE - Prêts VSLA
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('loans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')->constrained('groups')->onDelete('cascade');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->decimal('montant', 15, 2)->comment('Montant du prêt en FBU');
            $table->decimal('taux_interet', 5, 2)->default(0)->comment('Taux d\'intérêt en pourcentage');
            $table->integer('duree')->comment('Durée en mois');
            $table->decimal('montant_rembourse', 15, 2)->default(0)->comment('Montant déjà remboursé');
            $table->decimal('montant_restant', 15, 2)->comment('Montant restant à rembourser');
            $table->text('motif')->comment('Raison du prêt');
            $table->enum('statut', ['en_attente', 'approuve', 'rejete', 'actif', 'rembourse', 'defaut'])->default('en_attente');
            $table->foreignId('approuve_par')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('approved_at')->nullable();
            $table->date('date_echeance')->nullable();
            $table->date('date_remboursement_complet')->nullable();
            $table->text('notes_approbation')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // Index
            $table->index('group_id');
            $table->index('user_id');
            $table->index('statut');
            $table->index('date_echeance');
            $table->index('approuve_par');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('loans');
    }
};
