<?php

namespace App\EventListener;

use App\Exception\OfferUnavailableException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Serializer\NameConverter\NameConverterInterface;
use Symfony\Component\Validator\Exception\ValidationFailedException;

#[AsEventListener]
final class ApiExceptionListener
{
    private const PROBLEM_CONTENT_TYPE = 'application/problem+json';

    public function __construct(
        #[Autowire(service: 'serializer.name_converter.camel_case_to_snake_case')]
        private readonly NameConverterInterface $nameConverter,
    ) {
    }

    public function __invoke(ExceptionEvent $event): void
    {
        if (!str_starts_with($event->getRequest()->getPathInfo(), '/api')) {
            return;
        }

        $throwable = $event->getThrowable();
        $validationFailure = $this->validationFailure($throwable);

        if ($validationFailure instanceof ValidationFailedException) {
            $event->setResponse($this->validationProblem($validationFailure));

            return;
        }

        if ($throwable instanceof OfferUnavailableException) {
            $event->setResponse($this->problem(Response::HTTP_CONFLICT, [
                'type' => 'about:blank',
                'title' => Response::$statusTexts[Response::HTTP_CONFLICT],
                'status' => Response::HTTP_CONFLICT,
                'detail' => $throwable->getMessage(),
            ]));

            return;
        }

        if (!$throwable instanceof HttpExceptionInterface) {
            return;
        }

        $status = $throwable->getStatusCode();

        $event->setResponse($this->problem($status, [
            'type' => 'about:blank',
            'title' => Response::$statusTexts[$status] ?? 'Error',
            'status' => $status,
            'detail' => $throwable instanceof NotFoundHttpException
                ? 'Resource not found.'
                : $throwable->getMessage(),
        ], $throwable->getHeaders()));
    }

    private function validationFailure(\Throwable $throwable): ?ValidationFailedException
    {
        if ($throwable instanceof ValidationFailedException) {
            return $throwable;
        }

        $previous = $throwable->getPrevious();

        return $previous instanceof ValidationFailedException ? $previous : null;
    }

    private function validationProblem(ValidationFailedException $exception): JsonResponse
    {
        $violations = [];
        $details = [];

        foreach ($exception->getViolations() as $violation) {
            $path = $this->snakeCasePath($violation->getPropertyPath());
            $message = (string) $violation->getMessage();

            $violations[] = [
                'property_path' => $path,
                'title' => $message,
            ];

            $details[] = '' === $path ? $message : \sprintf('%s: %s', $path, $message);
        }

        return $this->problem(Response::HTTP_UNPROCESSABLE_ENTITY, [
            'type' => 'https://symfony.com/errors/validation',
            'title' => 'Validation Failed',
            'status' => Response::HTTP_UNPROCESSABLE_ENTITY,
            'detail' => implode("\n", $details),
            'violations' => $violations,
        ]);
    }

    private function snakeCasePath(string $propertyPath): string
    {
        return preg_replace_callback(
            '/[A-Za-z_][A-Za-z0-9_]*/',
            fn (array $matches): string => $this->nameConverter->normalize($matches[0]),
            $propertyPath,
        ) ?? $propertyPath;
    }

    /**
     * @param array<string, mixed>  $body
     * @param array<string, string> $headers
     */
    private function problem(int $status, array $body, array $headers = []): JsonResponse
    {
        $response = new JsonResponse($body, $status, $headers);
        $response->setEncodingOptions(\JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE);
        $response->headers->set('Content-Type', self::PROBLEM_CONTENT_TYPE);

        return $response;
    }
}
