<?php

namespace App\Services\Escola;

use App\Models\Escola;
use App\Services\GoogleMainService;
use Google\Service\Classroom\Course;

class EscolaSyncService
{
    public function __construct(
        protected GoogleMainService $googleService
    ) {}

    public function syncEscolas(): void
    {
        $client = $this->googleService->getGoogleClient();
        $classroom = app(\Google\Service\Classroom::class, ['client' => $client]);

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
}
