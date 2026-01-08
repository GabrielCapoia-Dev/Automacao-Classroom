<?php

namespace App\Services\Escola;

use App\Models\GoogleAccount;
use Google\Service\Classroom;
use Google\Service\Classroom\Course;
use RuntimeException;

class EscolaClassService
{
    protected function classroom(): Classroom
    {
        return app(Classroom::class);
    }

    public function criar(string $nome, GoogleAccount $account): string
    {
        $course = new Course([
            'name' => $nome,
            'ownerId' => 'me',
            'courseState' => 'ACTIVE',
        ]);


        try {
            $created = $this->classroom()->courses->create($course);
            return $created->id;
        } catch (\Throwable $e) {
            throw new RuntimeException(
                'Erro ao criar curso no Google Classroom: ' . $e->getMessage(),
                previous: $e
            );
        }
    }

    public function atualizar(string $classroomCourseId, string $nome): void
    {
        $course = new Course(['name' => $nome]);

        try {
            $this->classroom()->courses->patch(
                $classroomCourseId,
                $course,
                ['updateMask' => 'name']
            );
        } catch (\Throwable $e) {
            throw new RuntimeException(
                'Erro ao atualizar curso no Google Classroom: ' . $e->getMessage(),
                previous: $e
            );
        }
    }

    public function arquivar(string $classroomCourseId): void
    {
        $course = new Course(['courseState' => 'ARCHIVED']);

        try {
            $this->classroom()->courses->patch(
                $classroomCourseId,
                $course,
                ['updateMask' => 'courseState']
            );
        } catch (\Throwable $e) {
            throw new RuntimeException(
                'Erro ao arquivar curso no Google Classroom: ' . $e->getMessage(),
                previous: $e
            );
        }
    }
}
