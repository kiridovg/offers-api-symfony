<?php

namespace App\EventListener;

use App\Message\ProcessImport;
use App\Service\ImportProcessor;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;
use Symfony\Component\Messenger\Exception\HandlerFailedException;

#[AsEventListener]
final class ImportFailureListener
{
    public function __construct(private readonly ImportProcessor $processor)
    {
    }

    public function __invoke(WorkerMessageFailedEvent $event): void
    {
        if ($event->willRetry()) {
            return;
        }

        $message = $event->getEnvelope()->getMessage();

        if (!$message instanceof ProcessImport) {
            return;
        }

        $this->processor->markFailed($message->importId, $this->reason($event->getThrowable()));
    }

    private function reason(\Throwable $throwable): string
    {
        if (!$throwable instanceof HandlerFailedException) {
            return $throwable->getMessage();
        }

        $handlerFailure = $throwable->getPrevious();

        return $handlerFailure instanceof \Throwable ? $handlerFailure->getMessage() : $throwable->getMessage();
    }
}
