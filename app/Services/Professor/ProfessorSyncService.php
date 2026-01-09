<?php

namespace App\Services\Professor;

use App\Models\Escola;
use App\Models\Professor;
use App\Services\GoogleMainService;
use Google\Service\Classroom;
use Google\Service\Classroom\Student;

class ProfessorSyncService
{
    public function __construct(
        protected GoogleMainService $googleService
    ) {}

    /**
     * Sincroniza os "professores" (students do Classroom)
     * de todas as escolas já sincronizadas
     */
    public function syncProfessores(): void
    {
        $client = $this->googleService->getGoogleClient();
        $classroom = app(Classroom::class, ['client' => $client]);

        Escola::query()
            ->whereNotNull('classroom_course_id')
            ->each(function (Escola $escola) use ($classroom) {
                $this->syncProfessoresDaEscola($classroom, $escola);
            });
    }

    protected function syncProfessoresDaEscola(Classroom $classroom, Escola $escola): void
    {
        $pageToken = null;

        do {
            $response = $classroom->courses_students->listCoursesStudents(
                $escola->classroom_course_id,
                [
                    'pageSize'  => 100,
                    'pageToken' => $pageToken,
                ]
            );

            /** @var Student[] $students */
            $students = $response->getStudents() ?? [];

            foreach ($students as $student) {
                $profile = $student->getProfile();

                Professor::updateOrCreate(
                    [
                        'classroom_user_id' => $student->getUserId(),
                        'escola_id' => $escola->id,
                    ],
                    [
                        'google_account_id' => $this->googleService->getMainAccount()->id,
                        'nome'  => $profile->getName()->getFullName(),
                        'email' => $profile->getEmailAddress(),
                    ]
                );
            }

            $pageToken = $response->getNextPageToken();
        } while ($pageToken);
    }
}
