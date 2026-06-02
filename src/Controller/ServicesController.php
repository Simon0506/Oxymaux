<?php

namespace App\Controller;

use App\Entity\Service;
use App\Form\ServiceType;
use App\Repository\PriceKmRepository;
use App\Repository\ServiceRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class ServicesController extends AbstractController
{
    #[Route('/services', name: 'app_services')]
    public function services(ServiceRepository $serviceRepository, PriceKmRepository $priceKmRepository): Response
    {
        $services = $serviceRepository->sortByPosition();
        $tarifsByService = [];
        foreach ($services as $service) {
            $tarifs = $service->getTarifs()->toArray();
            usort($tarifs, function ($a, $b) {
                return $a->getPosition() <=> $b->getPosition();
            });
            $tarifsByService[$service->getId()] = $tarifs;
        }
        $priceKms = $priceKmRepository->sortByLength();
        return $this->render('home/services.html.twig', [
            'services' => $services,
            'tarifsByService' => $tarifsByService,
            'priceKms' => $priceKms,
        ]);
    }

    #[Route('/services/{id}/edit', name: 'app_services_edit', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function edit(ServiceRepository $serviceRepository, int $id, Request $request, EntityManagerInterface $em): Response
    {
        $service = $serviceRepository->find($id);
        $form = $this->createForm(ServiceType::class, $service);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $imageFile = $form->get('image')->getData();
            $imageOld = $service->getImage() ?? null;
            if ($imageFile && $imageFile !== $imageOld) {
                $originalFilename = pathinfo($imageFile->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename = transliterator_transliterate('Any-Latin; Latin-ASCII; [^A-Za-z0-9_] remove; Lower()', $originalFilename);
                $newFilename = $safeFilename . '-' . uniqid() . '.' . $imageFile->guessExtension();
                try {
                    $imageFile->move(
                        $this->getParameter('images_directory'),
                        $newFilename
                    );
                    $service->setImage($newFilename);
                } catch (FileException $e) {
                    // Gérer l'erreur de téléchargement de fichier
                }
            }
            $logoFile = $form->get('logo')->getData();
            $logoOld = $service->getLogo() ?? null;
            if ($logoFile && $logoFile !== $logoOld) {
                $originalFilename = pathinfo($logoFile->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename = transliterator_transliterate('Any-Latin; Latin-ASCII; [^A-Za-z0-9_] remove; Lower()', $originalFilename);
                $newFilename = $safeFilename . '-' . uniqid() . '.' . $logoFile->guessExtension();
                try {
                    $logoFile->move(
                        $this->getParameter('images_directory'),
                        $newFilename
                    );
                    $service->setLogo($newFilename);
                } catch (FileException $e) {
                    // Gérer l'erreur de téléchargement de fichier
                }
            }
            $tarifs = $service->getTarifs()->toArray();

            usort($tarifs, fn($a, $b) => $a->getPosition() <=> $b->getPosition());

            foreach ($tarifs as $i => $tarif) {
                $tarif->setPosition($i + 1);
            }

            $service->getTarifs()->clear();

            foreach ($tarifs as $tarif) {
                $service->addTarif($tarif);
            }
            $em->persist($service);
            $em->flush();
            $this->addFlash('success', 'Le service a été mis à jour avec succès.');
            return $this->redirectToRoute('app_services');
        }
        return $this->render('admin/services_edit.html.twig', [
            'service' => $service,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/services/new', name: 'app_services_new', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function new(Request $request, ServiceRepository $serviceRepository, EntityManagerInterface $em): Response
    {
        $service = new Service();
        $form = $this->createForm(ServiceType::class, $service);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $imageFile = $form->get('image')->getData();
            if ($imageFile) {
                $originalFilename = pathinfo($imageFile->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename = transliterator_transliterate('Any-Latin; Latin-ASCII; [^A-Za-z0-9_] remove; Lower()', $originalFilename);
                $newFilename = $safeFilename . '-' . uniqid() . '.' . $imageFile->guessExtension();
                try {
                    $imageFile->move(
                        $this->getParameter('images_directory'),
                        $newFilename
                    );
                    $service->setImage($newFilename);
                } catch (FileException $e) {
                    // Gérer l'erreur de téléchargement de fichier
                }
            }
            $logoFile = $form->get('logo')->getData();
            if ($logoFile) {
                $originalFilename = pathinfo($logoFile->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename = transliterator_transliterate('Any-Latin; Latin-ASCII; [^A-Za-z0-9_] remove; Lower()', $originalFilename);
                $newFilename = $safeFilename . '-' . uniqid() . '.' . $logoFile->guessExtension();
                try {
                    $logoFile->move(
                        $this->getParameter('images_directory'),
                        $newFilename
                    );
                    $service->setLogo($newFilename);
                } catch (FileException $e) {
                    // Gérer l'erreur de téléchargement de fichier
                }
            }
            $tarifs = $service->getTarifs()->toArray();

            usort($tarifs, fn($a, $b) => $a->getPosition() <=> $b->getPosition());

            foreach ($tarifs as $i => $tarif) {
                $tarif->setPosition($i + 1);
                if ($tarif->getService() === null) {
                    $tarif->setService($service);
                }
            }
            $service->setPosition(count($serviceRepository->findAll()) + 1);
            $em->persist($service);
            $em->flush();
            $this->addFlash('success', 'Le service a été créé avec succès.');
            return $this->redirectToRoute('app_services');
        }
        return $this->render('admin/services_new.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/services/{id}/delete', name: 'app_services_delete')]
    #[IsGranted('ROLE_ADMIN')]
    public function delete(ServiceRepository $serviceRepository, int $id, EntityManagerInterface $em): Response
    {
        $service = $serviceRepository->find($id);
        if ($service) {
            $em->remove($service);
            $em->flush();
            $this->addFlash('success', 'Le service a été supprimé avec succès.');
        }
        return $this->redirectToRoute('app_services');
    }

    #[Route('/services/sort', name: 'app_services_sort', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function sort(Request $request, ServiceRepository $serviceRepository, EntityManagerInterface $em): Response
    {
        $services = $serviceRepository->sortByPosition();
        if ($request->isMethod('POST')) {
            $positions = $request->request->all('services');
            foreach ($positions as $id => $position) {
                $service = $serviceRepository->find($id);
                if ($service) {
                    $service->setPosition((int)$position['position']);
                    $em->persist($service);
                }
            }
            $em->flush();
            $this->addFlash('success', 'Les services ont été réorganisés avec succès.');
            return $this->redirectToRoute('app_services');
        }
        return $this->render('admin/services_sort.html.twig', [
            'services' => $services,
        ]);
    }
}
