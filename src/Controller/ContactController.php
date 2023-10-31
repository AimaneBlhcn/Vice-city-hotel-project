<?php

namespace App\Controller;

use App\Form\ContactType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Annotation\Route;

class ContactController extends AbstractController
{
    #[Route('/contact', name: 'app_contact')]
    public function index(Request $request, MailerInterface $mailer): Response
    {

        $form = $this->createform(ContactType::class);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()){

            $data = $form->getData();

            $address = $data['email'];
            $content = $data['content'];

            $email = (new Email())
            ->from($address)
            ->to('no_reply_app@outlook.com')
            ->subject('Demande de contact')
            ->text($content);

            $mailer->send($email);

             // Message flash pour indiquer que le message a bien été envoyé
            $this->addFlash('success', 'Votre message a bien été envoyé.');

            // à chaque soumission on récréer le formulaire pour effacer les données
            $form = $this->createForm(ContactType::class);
        }

        return $this->render('contact/index.html.twig', [
            'controller_name' => 'ContactController',
            'formulaire' => $form
        ]);
    }
}
