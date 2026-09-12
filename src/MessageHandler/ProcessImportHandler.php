<?php

namespace App\MessageHandler;

use App\Message\ProcessImport;
use App\Service\ImportProcessor;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class ProcessImportHandler
{
    public function __construct(private readonly ImportProcessor $processor)
    {
    }

    public function __invoke(ProcessImport $message): void
    {
        $this->processor->process($message->importId);
    }
}
