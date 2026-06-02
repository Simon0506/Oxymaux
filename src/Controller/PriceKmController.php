<?php

namespace App\Controller;

use App\Entity\PriceKm;
use App\Form\PriceKmType;
use App\Repository\PriceKmRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class PriceKmController extends AbstractController
{
    #[Route('/price-km/add', name: 'app_price_km_add', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function addPriceKm(Request $request, EntityManagerInterface $em): Response
    {
        $priceKm = new PriceKm();
        $form = $this->createForm(PriceKmType::class, $priceKm);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($priceKm);
            $em->flush();
            $this->addFlash('success', 'Les frais de déplacement ont été ajoutés avec succès.');
            return $this->redirectToRoute('app_services');
        }
        return $this->render('admin/price_km_add.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/price-km/{id}/edit', name: 'app_price_km_edit', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function editPriceKm(Request $request, EntityManagerInterface $em, PriceKmRepository $priceKmRepository, int $id): Response
    {
        $priceKm = $priceKmRepository->find($id);
        if (!$priceKm) {
            throw $this->createNotFoundException('Frais de déplacement non trouvé.');
        }

        $form = $this->createForm(PriceKmType::class, $priceKm);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($priceKm);
            $em->flush();
            $this->addFlash('success', 'Les frais de déplacement ont été modifiés avec succès.');
            return $this->redirectToRoute('app_services');
        }

        return $this->render('admin/price_km_edit.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/price-km/{id}/delete', name: 'app_price_km_delete')]
    #[IsGranted('ROLE_ADMIN')]
    public function deletePriceKm(Request $request, EntityManagerInterface $em, PriceKmRepository $priceKmRepository, int $id): Response
    {
        $priceKm = $priceKmRepository->find($id);
        if (!$priceKm) {
            throw $this->createNotFoundException('Frais de déplacement non trouvé.');
        }

        $em->remove($priceKm);
        $em->flush();
        $this->addFlash('success', 'Les frais de déplacement ont été supprimés avec succès.');
        return $this->redirectToRoute('app_services');
    }
}
