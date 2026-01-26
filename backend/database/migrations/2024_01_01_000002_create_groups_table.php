<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration: Création de la table groups
 * TWUNGURANE - Groupes d'épargne communautaire (Tontines, VSLA)
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('groups', function (Blueprint $table) {
            $table->id();
            $table->string('nom', 255);
            $table->enum('type', ['tontine', 'vsla', 'groupe-solidaire'])->default('vsla');
            $table->string('localisation', 255)->comment('Province, Commune');
            $table->string('province', 100)->nullable();
            $table->string('commune', 100)->nullable();
            $table->decimal('montant_contribution', 15, 2)->comment('Montant en FBU');
            $table->enum('frequence', ['hebdomadaire', 'mensuelle', 'quotidienne'])->default('mensuelle');
            $table->integer('duree_cycle')->default(12)->comment('Durée en mois');
            $table->integer('max_members')->default(20);
            $table->decimal('solde_total', 15, 2)->default(0)->comment('Solde total du groupe en FBU');
            $table->foreignId('created_by')->constrained('users')->onDelete('restrict');
            $table->text('description')->nullable();
            $table->enum('statut', ['actif', 'suspendu', 'termine'])->default('actif');
            $table->timestamps();
            $table->softDeletes();

            // Index
            $table->index('type');
            $table->index('statut');
            $table->index('created_by');
            $table->index(['province', 'commune']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('groups');
    }
};
