<?php

namespace App\Services\Professor;

use App\Models\Escola;
use App\Models\Turma;
use App\Models\Professor;
use App\Services\GoogleMainService;
use Google\Service\Classroom;
use Google\Service\Classroom\Student;
use App\Support\GoogleRetry;


class ProfessorSyncService
{
    use GoogleRetry;


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

    public function sincronizarVinculoProfessorTurma(): void
    {
        $client = $this->googleService->getGoogleClient();
        $classroom = app(Classroom::class, ['client' => $client]);

        Escola::whereNotNull('classroom_course_id')
            ->each(function (Escola $escola) use ($classroom) {

                // 🔥 UMA chamada só
                $response = $this->retry(function () use ($classroom, $escola) {
                    return $classroom->courses_courseWork->listCoursesCourseWork(
                        $escola->classroom_course_id,
                        [
                            'pageSize' => 100,
                            'orderBy'  => 'updateTime desc'
                        ]
                    );
                });

                $courseWorks = collect($response->getCourseWork() ?? []);

                if ($courseWorks->isEmpty()) {
                    return;
                }

                // Agrupar por topic
                $porTopic = $courseWorks->groupBy(
                    fn($cw) => $cw->getTopicId()
                );

                $turmas = Turma::where('escola_id', $escola->id)
                    ->whereNotNull('classroom_topic_id')
                    ->get();

                foreach ($turmas as $turma) {

                    $listaTopic = $porTopic[$turma->classroom_topic_id] ?? null;

                    if (!$listaTopic || $listaTopic->isEmpty()) {
                        continue;
                    }

                    $ultima = $listaTopic->first();

                    $professorIds = $this->resolverProfessoresDaAtividade(
                        $ultima,
                        $escola
                    );

                    $turma->professores()->sync($professorIds);
                }
            });
    }

    protected function processarTurma(
        Classroom $classroom,
        Escola $escola,
        Turma $turma
    ): void {

        $response = $this->retry(function () use ($classroom, $escola) {
            return $classroom->courses_courseWork->listCoursesCourseWork(
                $escola->classroom_course_id,
                [
                    'pageSize' => 50,
                    'orderBy'  => 'updateTime desc'
                ]
            );
        });

        $courseWorks = collect($response->getCourseWork() ?? [])
            ->where('topicId', $turma->classroom_topic_id);

        if ($courseWorks->isEmpty()) {
            return;
        }

        $ultima = $courseWorks->first();

        $professorIds = $this->resolverProfessoresDaAtividade(
            $ultima,
            $escola
        );

        // 🔥 aqui está o que você realmente quer
        $turma->professores()->sync($professorIds);
    }

    protected function resolverProfessoresDaAtividade(
        \Google\Service\Classroom\CourseWork $courseWork,
        Escola $escola
    ): array {

        if ($courseWork->getAssigneeMode() === 'INDIVIDUAL_STUDENTS') {

            $studentIds = optional(
                $courseWork->getIndividualStudentsOptions()
            )->getStudentIds() ?? [];

            return Professor::where('escola_id', $escola->id)
                ->whereIn('classroom_user_id', $studentIds)
                ->pluck('id')
                ->toArray();
        }

        // ALL_STUDENTS
        return Professor::where('escola_id', $escola->id)
            ->pluck('id')
            ->toArray();
    }


    protected function syncProfessoresDaEscola(Classroom $classroom, Escola $escola): void
    {
        $pageToken = null;

        $classroomUserIds = []; // <- armazenar IDs reais do Classroom

        do {
            $response = $this->retry(function () use ($classroom, $escola, $pageToken) {
                return $classroom->courses_students->listCoursesStudents(
                    $escola->classroom_course_id,
                    [
                        'pageSize'  => 100,
                        'pageToken' => $pageToken,
                    ]
                );
            });

            $students = $response->getStudents() ?? [];

            foreach ($students as $student) {

                $profile = $student->getProfile();
                $userId = $student->getUserId();

                $classroomUserIds[] = $userId;

                Professor::updateOrCreate(
                    [
                        'classroom_user_id' => $userId,
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

        // 🔥 Remover professores que NÃO estão mais no Classroom
        Professor::where('escola_id', $escola->id)
            ->whereNotIn('classroom_user_id', $classroomUserIds)
            ->delete();
    }
}
