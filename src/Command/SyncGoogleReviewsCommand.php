<?php

namespace App\Command;

use App\Service\GoogleReviewsSynchronizer;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:sync-google-reviews',
)]
class SyncGoogleReviewsCommand extends Command
{
    public function __construct(
        private GoogleReviewsSynchronizer $synchronizer,
    ) {
        parent::__construct();
    }

    protected function execute(
        InputInterface $input,
        OutputInterface $output
    ): int {

        $this->synchronizer->sync();

        $output->writeln('Avis Google synchronisés.');

        return Command::SUCCESS;
    }
}
