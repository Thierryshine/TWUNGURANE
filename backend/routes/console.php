<?php

/**
 * TWUNGURANE - Routes Console
 * 
 * Commandes Artisan personnalisées
 */

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

// Commande d'inspiration par défaut
Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Commande pour vérifier la santé du système
Artisan::command('twungurane:health', function () {
    $this->info('=== TWUNGURANE Health Check ===');
    
    // Vérifier la base de données
    try {
        \DB::connection()->getPdo();
        $this->info('✓ Database: Connected');
    } catch (\Exception $e) {
        $this->error('✗ Database: ' . $e->getMessage());
    }
    
    // Vérifier Redis
    try {
        \Redis::ping();
        $this->info('✓ Redis: Connected');
    } catch (\Exception $e) {
        $this->warn('⚠ Redis: ' . $e->getMessage());
    }
    
    // Vérifier le service Python
    try {
        $response = \Http::timeout(5)->get(config('services.python.url') . '/health');
        if ($response->successful()) {
            $this->info('✓ Python Service: Running');
        } else {
            $this->warn('⚠ Python Service: Not responding');
        }
    } catch (\Exception $e) {
        $this->warn('⚠ Python Service: ' . $e->getMessage());
    }
    
    $this->newLine();
    $this->info('Health check completed.');
    
})->purpose('Check system health (database, redis, python service)');

// Commande pour générer des données de test
Artisan::command('twungurane:seed-demo', function () {
    $this->info('Generating demo data for TWUNGURANE...');
    
    Artisan::call('db:seed', ['--class' => 'DemoSeeder']);
    
    $this->info('Demo data generated successfully!');
    
})->purpose('Generate demo data for testing');
