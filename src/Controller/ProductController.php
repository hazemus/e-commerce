<?php

namespace App\Controller;

use App\Entity\AddProductHistory;
use App\Entity\Product;
use App\Form\AddProductHistoryType;
use App\Form\ProductType;
use App\Form\ProductUpdateType;
use App\Repository\AddProductHistoryRepository;
use App\Repository\ProductRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\String\Slugger\SluggerInterface;

#[Route('/editor/product')]
final class ProductController extends AbstractController
{
    #[Route(name: 'app_product_index', methods: ['GET'])]
    public function index(ProductRepository $productRepository): Response
    {
        return $this->render('product/index.html.twig', [
            'products' => $productRepository->findAll(),
        ]);
    }

    #[Route('/new', name: 'app_product_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager, SluggerInterface $slugger): Response
    {
        $product = new Product();
        $form = $this->createForm(ProductType::class, $product);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $image = $form->get('image')->getData();
            if ($image){
                $originalName = pathinfo($image->getClientOriginalName(), PATHINFO_FILENAME);
                $safeName = $slugger->slug($originalName);
                $newFilename = $safeName.'-'.uniqid().'.'.$image->guessExtension();
                try {
                    $image->move(
                        $this->getParameter('image_dir'),
                        $newFilename
                    );
                }catch (FileException $e){}
                $product->setImage($newFilename);
            }
            $entityManager->persist($product);
            $entityManager->flush();

            $stockHistory = new AddProductHistory();
            $stockHistory->setQte($product->getStock());
            $stockHistory->setProduct($product);
            $stockHistory->setCreatedAt(new \DateTimeImmutable());
            $entityManager->persist($stockHistory);
            $entityManager->flush();

            $this->addFlash('success','Product added successfully');

            return $this->redirectToRoute('app_product_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('product/new.html.twig', [
            'product' => $product,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_product_show', methods: ['GET'])]
    public function show(Product $product): Response
    {
        return $this->render('product/show.html.twig', [
            'product' => $product,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_product_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Product $product, EntityManagerInterface $entityManager,SluggerInterface $slugger): Response
    {
        $form = $this->createForm(ProductUpdateType::class, $product);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            $image = $form->get('image')->getData();
            if ($image){
                $originalName = pathinfo($image->getClientOriginalName(), PATHINFO_FILENAME);
                $safeName = $slugger->slug($originalName);
                $newFilename = $safeName.'-'.uniqid().'.'.$image->guessExtension();
                try {
                    $image->move(
                        $this->getParameter('image_dir'),
                        $newFilename
                    );
                }catch (FileException $e){}
                $product->setImage($newFilename);
            }

            $entityManager->flush();
            $this->addFlash('success','Product edited successfully');

            return $this->redirectToRoute('app_product_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('product/edit.html.twig', [
            'product' => $product,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_product_delete', methods: ['POST'])]
    public function delete(Request $request, Product $product, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$product->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($product);
            $entityManager->flush();
            $this->addFlash('danger','Product deleted successfully');
        }

        return $this->redirectToRoute('app_product_index', [], Response::HTTP_SEE_OTHER);
    }
    #[Route('/add/product/{id}/stock', name: 'app_product_stock_add', methods: ['POST','GET'])]
    public function addStock($id,EntityManagerInterface $entityManager, Request $request, ProductRepository $productRepository): Response
    {
        $addStock = new AddProductHistory();
        $form = $this->createForm(AddProductHistoryType::class, $addStock);
        $form->handleRequest($request);

        $product = $productRepository->find($id);

        if ($form->isSubmitted() && $form->isValid()) {

            if($addStock->getQte()>0){

                $newQte = $product->getStock() + $addStock->getQte();
                $product->setStock($newQte);
                $addStock->setCreatedAt(new \DateTimeImmutable());
                $addStock->setProduct($product);
                $entityManager->persist($addStock);
                $entityManager->flush();
                $this->addFlash('success','Stock added successfully');
                return $this->redirectToRoute('app_product_index');
            }else{
                $this->addFlash('danger','Stock quantity cannot be less than zero');
                return $this->redirectToRoute('app_product_stock_add', ['id' => $product->getId()]);
            }

        }

        return $this->render('product/addStock.html.twig',
            ['form' => $form->createView(),
                'product' => $product,]);
    }
    #[Route('/add/product/{id}/stock/history', name: 'app_product_stock_add_history', methods: ['GET'])]
    public function productAddHistory($id, ProductRepository $productRepository, AddProductHistoryRepository $addProductHistoryRepository):Response
    {
        $product = $productRepository->find($id);
        $productAddedHistory = $addProductHistoryRepository->findBy(['product' => $product],['id'=>'DESC']);
        return $this->render('product/addedStockHistoryShow.html.twig',[
            "productsAdded"=>$productAddedHistory
        ]);
    }
   }
