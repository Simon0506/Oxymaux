<?php

namespace App\Controller;

use App\Entity\Animal;
use App\Entity\Category;
use App\Form\AnimalType;
use App\Repository\AnimalRepository;
use App\Repository\CategoryRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class AnimalController extends AbstractController
{

    #[Route('/animaux', name: 'app_animaux')]
    public function animaux(CategoryRepository $categoryRepository): Response
    {
        $categories = $categoryRepository->findAllNotEmpty();
        return $this->render('home/animaux.html.twig', [
            'categories' => $categories,
        ]);
    }

    #[Route('/animaux/new', name: 'app_animaux_new')]
    #[IsGranted('ROLE_ADMIN')]
    public function newAnimal(Request $request, CategoryRepository $categoryRepository, EntityManagerInterface $em): Response
    {
        $animal = new Animal();
        $form = $this->createForm(AnimalType::class, $animal);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            $category = $form->get('category')->getData();
            $newCategoryName = $form->get('newCategory')->getData();

            // 🟡 CAS 1 : création nouvelle catégorie
            if (!$category && $newCategoryName) {

                $category = $categoryRepository->findOneBy(['name' => $newCategoryName]);

                if (!$category) {
                    $category = new Category();
                    $category->setName($newCategoryName);

                    $em->persist($category);
                }
            }

            // 🔗 liaison avec l'animal
            $animal->setCategory($category);

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
                    $animal->setImage($newFilename);
                } catch (FileException $e) {
                    // Gérer l'erreur de téléchargement de fichier
                }
            }

            $em->persist($animal);
            $em->flush();

            return $this->redirectToRoute('app_animaux');
        }
        return $this->render('admin/animaux_new.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/animaux/{id}/edit', name: 'app_animaux_edit')]
    #[IsGranted('ROLE_ADMIN')]
    public function editAnimal(AnimalRepository $animalRepository, CategoryRepository $categoryRepository, EntityManagerInterface $em, int $id, Request $request): Response
    {
        $animal = $animalRepository->find($id);
        $form = $this->createForm(AnimalType::class, $animal);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            $category = $form->get('category')->getData();
            $newCategoryName = $form->get('newCategory')->getData();

            // 🟡 CAS 1 : création nouvelle catégorie
            if (!$category && $newCategoryName) {

                $category = $categoryRepository->findOneBy(['name' => $newCategoryName]);

                if (!$category) {
                    $category = new Category();
                    $category->setName($newCategoryName);

                    $em->persist($category);
                }
            }

            // 🔗 liaison avec l'animal
            $animal->setCategory($category);

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
                    $animal->setImage($newFilename);
                } catch (FileException $e) {
                    // Gérer l'erreur de téléchargement de fichier
                }
            }

            $em->persist($animal);
            $em->flush();

            return $this->redirectToRoute('app_animaux');
        }
        return $this->render('admin/animaux_edit.html.twig', [
            'animal' => $animal,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/animaux/{id}/delete', name: 'app_animaux_delete')]
    #[IsGranted('ROLE_ADMIN')]
    public function deleteAnimal(AnimalRepository $animalRepository, EntityManagerInterface $em, int $id): Response
    {
        $animal = $animalRepository->find($id);
        if ($animal) {
            $em->remove($animal);
            $em->flush();
            $this->addFlash('success', 'L\'animal a été supprimé avec succès.');
        }
        return $this->redirectToRoute('app_animaux');
    }
}
