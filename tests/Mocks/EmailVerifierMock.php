<?php

namespace App\Tests\Mocks;

use App\Security\EmailVerifier;

class EmailVerifierMock extends EmailVerifier
{
    // On surcharge la méthode pour qu'elle ne fasse rien (ou qu'on puisse la vérifier)
    public function sendEmailConfirmation(string $verifyEmailRouteName, $user, $emailTemplatedMessage): void
    {
        // Ici, tu peux ajouter une logique pour vérifier que c'est bien appelé
        // Ou laisser vide pour simuler le succès sans envoyer de vrai mail
    }
}
