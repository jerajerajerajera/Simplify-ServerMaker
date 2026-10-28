<?php

// Stand-ins for the few Laravel classes the pure code throws, so tests run without a panel.

declare(strict_types=1);

namespace Illuminate\Validation;

if (! class_exists(ValidationException::class)) {
    class ValidationException extends \Exception
    {
        public array $messages = [];

        public static function withMessages(array $messages): self
        {
            $exception = new self((string) reset($messages));
            $exception->messages = $messages;

            return $exception;
        }

        public function errors(): array
        {
            return $this->messages;
        }
    }
}
