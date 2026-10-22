<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Création des tables utilisateurs et authentification.
     */
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();

            $table->string('firstname', 100);
            $table->string('lastname', 100);

            $table->string('email', 150)->unique();
            $table->string('telephone', 20)->nullable()->unique();

            $table->string('password');

            $table->string('role', 30)->default('PARENT');
            $table->string('status', 20)->default('ACTIF');

            $table->timestamp('email_verified_at')->nullable();

            $table->rememberToken();

            $table->timestamps();
        });

        // Contrôle des rôles autorisés
        DB::statement("
            ALTER TABLE users
            ADD CONSTRAINT users_role_check
            CHECK (
                role IN (
                    'DIRECTEUR',
                    'ENSEIGNANT',
                    'SECRETARIAT',
                    'COMPTABILITE',
                    'PARENT'
                )
            )
        ");

        // Contrôle des statuts autorisés.
        DB::statement("
            ALTER TABLE users
            ADD CONSTRAINT users_status_check
            CHECK (
                status IN ('ACTIF', 'INACTIF')
            )
        ");

        // Réinitialisation des mots de passe.
        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        // Sessions des utilisateurs.
        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    /**
     * Suppression des tables créées par cette migration.
     */
    public function down(): void
    {
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('users');
    }
};
