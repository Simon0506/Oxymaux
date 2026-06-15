<?php

namespace App\Command;

use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

#[AsCommand(
    name: 'app:warn-inactive-users',
    description: 'Envoie un e-mail d\'avertissement aux utilisateurs inactifs depuis bientôt 3 ans.',
)]
class WarnInactiveUsersCommand extends Command
{
    public function __construct(
        private UserRepository $userRepository,
        private EntityManagerInterface $em,
        private MailerInterface $mailer,
        private UrlGeneratorInterface $urlGenerator
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        // Définition de la fourchette d'un mois (le 36ème mois d'inactivité)
        $threeYearsAgo = new \DateTimeImmutable('-3 years');
        $twoYearsAndElevenMonthsAgo = new \DateTimeImmutable('-35 months');

        $usersToWarn = $this->userRepository->findUsersToWarnBeforeDeletion($threeYearsAgo, $twoYearsAndElevenMonthsAgo);

        if (empty($usersToWarn)) {
            $io->success('Aucun utilisateur à avertir aujourd’hui.');
            return Command::SUCCESS;
        }

        $io->progressStart(count($usersToWarn));
        $loginUrl = $this->urlGenerator->generate('app_login', [], UrlGeneratorInterface::ABSOLUTE_URL);

        foreach ($usersToWarn as $user) {
            try {
                $email = (new Email())
                    ->from('oxymaux@gmail.com')
                    ->to($user->getEmail())
                    ->subject('Votre compte Oxymaux va bientôt expirer')
                    ->text(sprintf(
                        "Bonjour %s,\n\nNous avons remarqué que vous ne vous êtes pas connecté à votre compte Oxymaux depuis bientôt 3 ans.\n\nConformément à la réglementation RGPD sur la protection des données, sans action de votre part, votre compte ainsi que vos données associées seront définitivement supprimés dans 30 jours.\n\nSi vous souhaitez conserver votre compte et vos historiques, il vous suffit de vous connecter avant cette échéance en cliquant sur le lien suivant :\n%s\n\nÀ bientôt chez Oxymaux !\nL'équipe Oxymaux",
                        $user->getFirstName(),
                        $loginUrl
                    ));

                $this->mailer->send($email);

                // On marque l'utilisateur pour qu'il ne reçoive plus jamais ce mail
                $user->setWarnedForDeletionAt(new \DateTimeImmutable());
            } catch (\Exception $e) {
                $io->error(sprintf('Impossible d’envoyer l’e-mail à %s : %s', $user->getEmail(), $e->getMessage()));
            }

            $io->progressAdvance();
        }

        // On enregistre tous les flags en BDD d'un seul coup
        $this->em->flush();

        $io->progressFinish();
        $io->success(sprintf('%d utilisateurs ont été avertis avec succès.', count($usersToWarn)));

        return Command::SUCCESS;
    }
}
