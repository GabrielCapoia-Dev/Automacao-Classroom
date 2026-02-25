<?php
// app/Support/GoogleRetry.php

namespace App\Support;

trait GoogleRetry
{
    protected function retry(callable $callback, int $maxAttempts = 5)
    {
        $attempt = 0;
        $delay = 1;

        beginning:
        try {
            return $callback();
        } catch (\Google\Service\Exception $e) {

            $attempt++;

            $errors = $e->getErrors();
            $reason = $errors[0]['reason'] ?? null;
            $code = $e->getCode();

            if (
                $attempt < $maxAttempts &&
                (
                    in_array($reason, ['backendError', 'internalError', 'rateLimitExceeded'])
                    || in_array($code, [500, 503])
                )
            ) {
                sleep($delay);
                $delay *= 2;
                goto beginning;
            }

            throw $e;
        }
    }
}
