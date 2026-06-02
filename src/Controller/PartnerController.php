<?php

namespace App\Controller;

use App\Form\PartnerType;
use App\Repository\PartnerRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class PartnerController extends AbstractController
{
    #[Route('/partners/new', name: 'app_partners_new')]
    #[IsGranted('ROLE_ADMIN')]
    public function newPartner(Request $request, EntityManagerInterface $em): Response
    {
        $form = $this->createForm(PartnerType::class);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $partner = $form->getData();
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
                    $partner->setLogo($newFilename);
                } catch (FileException $e) {
                    // Gérer l'erreur de téléchargement de fichier
                }
            }
            $em->persist($partner);
            $em->flush();
            $this->addFlash('success', 'Le partenaire a été ajouté avec succès.');
            return $this->redirectToRoute('app_home');
        } elseif ($form->isSubmitted()) {
            $this->addFlash('error', 'Erreur lors de l\'ajout du partenaire. Veuillez vérifier les données saisies.');
            return $this->redirectToRoute('app_partners_new');
        }
        return $this->render('admin/partners_new.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/partners/{id}/edit', name: 'app_partners_edit')]
    #[IsGranted('ROLE_ADMIN')]
    public function editPartner(Request $request, EntityManagerInterface $em, PartnerRepository $partnerRepository, int $id): Response
    {
        $partner = $partnerRepository->find($id);
        if (!$partner) {
            throw $this->createNotFoundException('Partenaire non trouvé');
        }
        $form = $this->createForm(PartnerType::class, $partner);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
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
                    $partner->setLogo($newFilename);
                } catch (FileException $e) {
                    // Gérer l'erreur de téléchargement de fichier
                }
            }
            $em->persist($partner);
            $em->flush();
            $this->addFlash('success', 'Le partenaire a été modifié avec succès.');
            return $this->redirectToRoute('app_home');
        } elseif ($form->isSubmitted()) {
            $this->addFlash('error', 'Erreur lors de la modification du partenaire. Veuillez vérifier les données saisies.');
            return $this->redirectToRoute('app_partners_edit', ['id' => $id]);
        }
        return $this->render('admin/partners_edit.html.twig', [
            'form' => $form->createView(),
            'partner' => $partner,
        ]);
    }

    #[Route('/partners/{id}/delete', name: 'app_partners_delete')]
    #[IsGranted('ROLE_ADMIN')]
    public function deletePartner(EntityManagerInterface $em, PartnerRepository $partnerRepository, int $id): Response
    {
        $partner = $partnerRepository->find($id);
        if (!$partner) {
            throw $this->createNotFoundException('Partenaire non trouvé');
        }
        $em->remove($partner);
        $em->flush();
        $this->addFlash('success', 'Le partenaire a été supprimé avec succès.');
        return $this->redirectToRoute('app_home');
    }
}
