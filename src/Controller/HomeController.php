<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class HomeController extends AbstractController
{
    #[Route('/', name: 'app_home')]
    public function index(): Response
    {
        return $this->render('home/index.html.twig');
    }

    #[Route('/parcours', name: 'app_parcours')]
    public function parcours(): Response
    {
        return $this->render('home/parcours.html.twig');
    }

    #[Route('/animaux', name: 'app_animaux')]
    public function animaux(): Response
    {
        return $this->render('home/animaux.html.twig');
    }

    #[Route('/services', name: 'app_services')]
    public function services(): Response
    {
        return $this->render('home/services.html.twig');
    }

    #[Route('/tarifs', name: 'app_tarifs')]
    public function tarifs(): Response
    {
        return $this->render('home/tarifs.html.twig');
    }

    #[Route('/planning', name: 'app_planning')]
    public function planning(): Response
    {
        return $this->render('home/planning.html.twig');
    }

    #[Route('/contact', name: 'app_contact')]
    public function contact(): Response
    {
        return $this->render('home/contact.html.twig');
    }
}
