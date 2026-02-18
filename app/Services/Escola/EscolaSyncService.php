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
        $courseIds = [];
        $mainAccountId = $this->googleService->getMainAccount()->id;

        do {
            $response = $classroom->courses->listCourses([
                'pageSize' => 100,
                'pageToken' => $pageToken,
                'courseStates' => ['ACTIVE', 'ARCHIVED'],
            ]);

            $courses = $response->getCourses() ?? [];

            foreach ($courses as $course) {

                $courseId = $course->getId();
                $courseIds[] = $courseId;

                Escola::updateOrCreate(
                    ['classroom_course_id' => $courseId],
                    [
                        'google_account_id' => $mainAccountId,
                        'nome' => $course->getName(),
                    ]
                );
            }

            $pageToken = $response->getNextPageToken();
        } while ($pageToken);

        // 🔥 remover escolas que não existem mais no Classroom
        Escola::where('google_account_id', $mainAccountId)
            ->whereNotIn('classroom_course_id', $courseIds)
            ->delete();
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

        $topicIds = [];

        do {
            $response = $this->retry(function () use ($classroom, $escola, $pageToken) {
                return $classroom->courses_topics->listCoursesTopics(
                    $escola->classroom_course_id,
                    [
                        'pageSize' => 100,
                        'pageToken' => $pageToken,
                    ]
                );
            });

            $topics = $response->getTopic() ?? [];

            foreach ($topics as $topic) {

                $nomeTurma = trim($topic->getName());
                if ($nomeTurma === '') {
                    continue;
                }

                $topicId = $topic->getTopicId();
                $topicIds[] = $topicId;

                $serieNome = $this->normalizeSerieName($nomeTurma);

                $serie = \App\Models\Serie::firstOrCreate(
                    [
                        'google_account_id' => $mainAccountId,
                        'nome' => $serieNome
                    ]
                );

                Turma::updateOrCreate(
                    [
                        'classroom_topic_id' => $topicId,
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

        // 🔥 remover turmas que não existem mais no Classroom
        Turma::where('escola_id', $escola->id)
            ->whereNotIn('classroom_topic_id', $topicIds)
            ->delete();
    }


    protected function retry(callable $callback, int $maxAttempts = 5)
    {
        $attempt = 0;
        $delay = 1; // segundos

        beginning:
        try {
            return $callback();
        } catch (\Google\Service\Exception $e) {
            $attempt++;

            $errors = $e->getErrors();
            $reason = $errors[0]['reason'] ?? null;

            if (
                $attempt < $maxAttempts &&
                in_array($reason, ['backendError', 'internalError', 'rateLimitExceeded'])
            ) {
                sleep($delay);
                $delay *= 2; // exponential backoff
                goto beginning;
            }

            throw $e;
        }
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
