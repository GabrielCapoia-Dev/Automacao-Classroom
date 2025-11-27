<?php

namespace Database\Seeders;

use App\Models\DominioEmail;
use App\Models\Serie;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Limpa cache das permissões do Spatie
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Lista de permissões que serão atribuídas à role Admin
        $permissionsList = [
            'Listar Usuários',
            'Criar Usuários',
            'Editar Usuários',
            'Excluir Usuários',
            'Listar Níveis de Acesso',
            'Criar Níveis de Acesso',
            'Editar Níveis de Acesso',
            'Excluir Níveis de Acesso',
            'Listar Permissões de Execução',
            'Criar Permissões de Execução',
            'Editar Permissões de Execução',
            'Excluir Permissões de Execução',
            'Listar Dominios de Email',
            'Criar Dominios de Email',
            'Editar Dominios de Email',
            'Excluir Dominios de Email',
            'Listar Séries',
            'Criar Séries',
            'Editar Séries',
            'Excluir Séries',
            'Listar Veículos',
            'Criar Veículos',
            'Editar Veículos',
            'Excluir Veículos',
            'Listar Escolas',
            'Criar Escolas',
            'Editar Escolas',
            'Excluir Escolas',
            'Listar Turmas',
            'Criar Turmas',
            'Editar Turmas',
            'Excluir Turmas',
            'Listar Alunos',
            'Criar Alunos',
            'Editar Alunos',
            'Excluir Alunos',
            'Listar Rotas',
            'Criar Rotas',
            'Editar Rotas',
            'Excluir Rotas',
        ];

        $secretarioPermissionsList = [
            'Listar Usuários',
            'Criar Usuários',
            'Editar Usuários',
            'Excluir Usuários',
            'Listar Turmas',
            'Criar Turmas',
            'Editar Turmas',
            'Excluir Turmas',
            'Listar Alunos',
            'Criar Alunos',
            'Editar Alunos',
            'Excluir Alunos',
        ];

        $usuarioPermissionsList = [
            'Listar Turmas',
            'Listar Alunos',
            'Editar Alunos',
        ];

        $password = "Senha@123";

        // Criação das permissões
        foreach ($permissionsList as $permissionName) {
            Permission::firstOrCreate(['name' => $permissionName]);
        }

        // Criação da rule Admin
        $adminRole = Role::firstOrCreate(['name' => 'Admin']);
        $secretarioRole = Role::firstOrCreate(['name' => 'Secretario']);
        $usuarioRole = Role::firstOrCreate(['name' => 'Usuario']);

        // Atribui todas as permissões à role Admin
        $adminRole->syncPermissions($permissionsList);
        $secretarioRole->syncPermissions($secretarioPermissionsList);
        $usuarioRole->syncPermissions($usuarioPermissionsList);

        $adminUser = User::firstOrCreate(
            ['email' => 'admin@admin.com'],
            [
                'name' => 'Admin',
                'password' => Hash::make($password),
                'email_verified_at' => now(),
                'email_approved' => true
            ]
        );

        $secretarioUser = User::firstOrCreate(
            ['email' => 'secretario@secretario.com'],
            [
                'name' => 'Secretario',
                'password' => Hash::make($password),
                'email_verified_at' => now(),
                'email_approved' => true
            ]
        );

        $usuarioUser = User::firstOrCreate(
            ['email' => 'usuario@usuario.com'],
            [
                'name' => 'Usuario',
                'password' => Hash::make($password),
                'email_verified_at' => now(),
                'email_approved' => true
            ]
        );

        $adminUser->assignRole($adminRole);
        $secretarioUser->assignRole($secretarioRole);
        $usuarioUser->assignRole($usuarioRole);


        /**
         * Criar domínios de email
         */

        $emailPermissionsList = [
            [
                'gmail.com',
                'edu.umuarama.pr.gov.br',
                'umuarama.pr.gov.br',
            ],
            [
                'Geral',
                'Educação',
                'Administrativo'
            ]
        ];

        foreach ($emailPermissionsList[0] as $index => $dominio) {
            $setor = $emailPermissionsList[1][$index] ?? 'Geral';

            DominioEmail::create([
                'dominio_email' => $dominio,
                'setor' => $setor,
                'status' => 1,
            ]);
        }

        $this->call([]);
    }
}
