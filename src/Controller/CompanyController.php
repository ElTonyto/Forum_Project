<?php

namespace App\Controller;

use App\Entity\Company;
use App\Entity\User;
use App\Entity\Slot;
use App\Entity\Training;
use App\Form\CompanyType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;

use App\Repository\CompanyRepository;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Doctrine\Common\Collections\ArrayCollection;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;

/**
 * @IsGranted("ROLE_USER")
 * @Route("/company")
 */
class CompanyController extends AbstractController
{
    /**
     * @Route("/", name="company_index", methods={"GET"})
     */
    public function index(CompanyRepository $companyRepository): Response
    {
        return $this->render('company/index.html.twig', [
            'companies' => $companyRepository->findAll(),
        ]);
    }

    // Créé les créneaux pour l'entreprise
    public function createSlots(Company $company)
    {
        // Récupération des paramètres des créneaux (voir .env)
        
        // Début et fin d'un créneau (par ex: 14:00 et 16:45)
        $slotsStart = getenv('SLOTS_START');
        $slotsEnd = getenv('SLOTS_END');

        // Durée d'un créneau en minutes (par exemple 15)
        $slotsDuration = getenv('SLOTS_DURATION');

        // Converted to seconds
        $slotsStartSecond = $this->convertToSecond($slotsStart);
        $slotsEndSecond = $this->convertToSecond($slotsEnd);
        $slotsDurationSecond = $slotsDuration * 60;

        $slotsQuantity = ( $slotsEndSecond - $slotsStartSecond ) / $slotsDurationSecond;

        for ($i = 0; $i < $slotsQuantity; $i++) {

            $slot = new Slot();
            $slot->setTime( $this->convertToString($slotsStartSecond) );
            $company->addSlot($slot);

            $entityManager = $this->getDoctrine()->getManager();
            $entityManager->persist($slot);
            $entityManager->flush();

            $slotsStartSecond += $slotsDurationSecond; 
        }
    }

    // Converts seconds to HH:MM format
    public function convertToString($seconds) {
        $hours = floor($seconds / 3600);
        $minutes = floor(($seconds / 60) % 60);

        if( $minutes === 0){
            return "$hours:$minutes"."0";
        }
        return "$hours:$minutes";
        
    }

    // Convert string format HH:MM to seconds
    public function convertToSecond($time){
        list($h, $m) = explode(':', $time);
	    return ($h * 3600) + ($m * 60);
    }

    /**
     * @Route("/new", name="company_new", methods={"GET","POST"})
     */
    public function new(Request $request): Response
    {
        $company = new Company();
        $trainingOptions = $this->getTrainingsOptions();

        $form = $this->createForm(CompanyType::class, $company);
        // Add training checkboxes
        $this->buildTrainingForm($trainingOptions, $form);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager = $this->getDoctrine()->getManager();
            $this->createSlots($company);
            $entityManager->persist($company);
            $entityManager->flush();

            return $this->redirectToRoute('company_index');
        }

        return $this->render('company/new.html.twig', [
            'company' => $company,
            'form' => $form->createView(),
        ]);
    }

    // Generates the training checkboxes for the form
    public function buildTrainingForm ( ArrayCollection $options, $form ) {
        $form->add('training', EntityType::class, [
            'class' => Training::class,
            'choice_label' => 'name',
            'choices' => $options,
            'multiple' => true,
            'expanded' => true
        ]);
    }

    // Gets all the training options
    public function getTrainingsOptions(){

        // Picks all the elements in Training
        $entityManager = $this->getDoctrine()->getManager();
        $trainings = $entityManager->getRepository(Training::class)->findAll();

        // Add it to options
        $options = new ArrayCollection();
        foreach($trainings as $training){
            $options->set($training->getName(), $training);
        }
        return $options;
    }

    /**
     * @Route("/details/{id}", name="company_details", methods={"GET"})
     */
    public function details(Company $company): Response
    {
        return $this->render('company/details.html.twig', [
            'company' => $company,
        ]);
    }

    /**
     * @Route("/{id}/edit", name="company_edit", methods={"GET","POST"})
     */
    public function edit(Request $request, Company $company): Response
    {
        $this->denyAccessUnlessGranted("ROLE_ADMIN");

        $trainingOptions = $this->getTrainingsOptions();    

        $form = $this->createForm(CompanyType::class, $company);
        // Add training checkboxes
        $this->buildTrainingForm($trainingOptions, $form);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->getDoctrine()->getManager()->flush();

            return $this->redirectToRoute('company_index');
        }

        return $this->render('company/edit.html.twig', [
            'company' => $company,
            'form' => $form->createView(),
        ]);
    }

    /**
     * @Route("/{id}", name="company_delete", methods={"DELETE"})
     */
    public function delete(Request $request, Company $company): Response
    {
        $this->denyAccessUnlessGranted("ROLE_ADMIN");

        if ($this->isCsrfTokenValid('delete'.$company->getId(), $request->request->get('_token'))) {
            $entityManager = $this->getDoctrine()->getManager();
            $entityManager->remove($company);
            $entityManager->flush();
        }

        return $this->redirectToRoute('company_index');
    }

    // Function to make the reservation of a free slot
     /**
     * @Route("/{userId}/{slotId}/add", name="addUserToSlot", methods={"GET","POST"})
     */
    public function addUserToSlot(int $userId, Slot $slot)
    {
        if(is_null($slot->getStudent)){
            $slot->setStudent($userId);
            $entityManager->persist($slot);
            $this->getDoctrine()->getManager()->flush();
        }
        return $this->redirectToRoute('company_index');
    }
}
