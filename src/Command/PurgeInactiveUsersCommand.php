<?php

namespace App\Command;

use App\Repository\UserRepository;
use App\Service\AccountDeletionService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:purge-inactive-users',
    description: 'Purge automatique des comptes inactifs depuis 3 ans pour la conformité RGPD.',
)]
class PurgeInactiveUsersCommand extends Command
{
    public function __construct(
        private UserRepository $userRepository,
        private AccountDeletionService $accountDeletionService
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        // Date pivot : Aujourd'hui moins 3 ans
        $limitDate = new \DateTimeImmutable('-3 years');

        // Récupération des comptes qui n'ont aucune activité depuis cette date
        $inactiveUsers = $this->userRepository->findUsersInactiveSince($limitDate);

        if (empty($inactiveUsers)) {
            $io->success('Aucun compte inactif à supprimer.');
            return Command::SUCCESS;
        }

        $io->progressStart(count($inactiveUsers));

        foreach ($inactiveUsers as $user) {
            try {
                // On force 'delete' pour nettoyer de vieilles réservations futures oubliées
                // isAutomatic est à true pour adapter les e-mails envoyés
                $this->accountDeletionService->deleteAccount($user, 'delete', true);
            } catch (\Exception $e) {
                // Si un utilisateur plante (ex: problème d'API Google Calendar), on log l'erreur
                // mais on ne bloque pas la boucle pour les autres comptes inactifs
                $io->error(sprintf('Erreur pour l’utilisateur %s : %s', $user->getEmail(), $e->getMessage()));
            }

            $io->progressAdvance();
        }

        $io->progressFinish();
        $io->success('La purge automatique des comptes inactifs (RGPD) s’est terminée avec succès.');

        return Command::SUCCESS;
    }
}
