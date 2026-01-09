<?php

namespace App\Services\Escola;

use App\Models\Escola;
use App\Models\Turma;
use App\Services\GoogleMainService;
use Google\Service\Classroom\Course;
use Google\Service\Classroom\Topic;
use Google\Service\Classroom;

class EscolaSyncService
{
    public function __construct(
        protected GoogleMainService $googleService
    ) {}

    /**
     * V1 - sincroniza apenas escolas (courses)
     */
    public function syncEscolas(): void
    {
        $classroom = $this->getClassroom();

        $pageToken = null;

        do {
            $response = $classroom->courses->listCourses([
                'pageSize' => 100,
                'pageToken' => $pageToken,
                'courseStates' => ['ACTIVE', 'ARCHIVED'],
            ]);

            /** @var Course[] $courses */
            $courses = $response->getCourses() ?? [];

            foreach ($courses as $course) {
                Escola::updateOrCreate(
                    ['classroom_course_id' => $course->getId()],
                    [
                        'google_account_id' => $this->googleService->getMainAccount()->id,
                        'nome' => $course->getName(),
                    ]
                );
            }

            $pageToken = $response->getNextPageToken();
        } while ($pageToken);
    }

    /**
     * V2 - sincroniza escolas E turmas (topics)
     */
    public function syncEscolasComTurmas(): void
    {
        // 1. Garante que as escolas estão sincronizadas
        $this->syncEscolas();

        $classroom = $this->getClassroom();

        // 2. Pega apenas escolas válidas (com course_id)
        $escolas = Escola::whereNotNull('classroom_course_id')->get();

        foreach ($escolas as $escola) {
            $this->syncTurmasDaEscola($classroom, $escola);
        }
    }

    /**
     * Sincroniza os topics (turmas) de uma escola
     */
    protected function syncTurmasDaEscola(Classroom $classroom, Escola $escola): void
    {
        $pageToken = null;
        $mainAccountId = $this->googleService->getMainAccount()->id;

        do {
            $response = $classroom->courses_topics->listCoursesTopics(
                $escola->classroom_course_id,
                [
                    'pageSize' => 100,
                    'pageToken' => $pageToken,
                ]
            );

            /** @var Topic[] $topics */
            $topics = $response->getTopic() ?? [];

            foreach ($topics as $topic) {
                $nomeTurma = trim($topic->getName());

                if ($nomeTurma === '') {
                    continue;
                }

                // 1️⃣ normaliza nome da série
                $serieNome = $this->normalizeSerieName($nomeTurma);

                // 2️⃣ cria ou recupera a série COM google_account_id
                $serie = \App\Models\Serie::firstOrCreate(
                    [
                        'google_account_id' => $mainAccountId,
                        'nome' => $serieNome
                    ]
                );

                // 3️⃣ cria ou atualiza a turma vinculando à série
                Turma::updateOrCreate(
                    [
                        'classroom_topic_id' => $topic->getTopicId(),
                        'escola_id' => $escola->id,
                    ],
                    [
                        'google_account_id' => $mainAccountId,
                        'nome' => $nomeTurma,
                        'serie_id' => $serie->id,
                    ]
                );
            }

            $pageToken = $response->getNextPageToken();
        } while ($pageToken);
    }


    /**
     * Centraliza a criação do client Classroom
     */
    protected function getClassroom(): Classroom
    {
        $client = $this->googleService->getGoogleClient();

        return app(Classroom::class, ['client' => $client]);
    }

    protected function normalizeSerieName(string $name): string
    {
        return mb_strtoupper(trim($name));
    }
}
