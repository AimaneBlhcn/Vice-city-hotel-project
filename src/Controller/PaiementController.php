<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use App\Form\PaiementType;
use App\Repository\ChambreRepository;
use App\Repository\ReservationRepository;
use App\Repository\UserRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Dompdf\Dompdf;

class PaiementController extends AbstractController
{

    // fonction qui permet d'enregistrer les données du formulaire au format CSV
    // on va insérer en paremetre les données du formulaires, cad la variable $dataForm = $form->getData();
    private function saveToCSVfile($dataFormulaire): void
    {
        // chemin vers lequel on veut enregistrer notre fichier CSV
        // $this->getParameter('kernel.project_dir'): c'est la racine du projet qui permet d'obtenir le chemin du répertoire du projet Symfony
        $cheminFichier = $this->getParameter('kernel.project_dir') . '/src/fichierCSV/paiement_data_' . date('d-m-y-h.i.s') . '.csv';

        // fopen() : permet d'ouvrir un fichier
        // 'w' : signifie "écriture"
        $fichier = fopen($cheminFichier, 'w');


        // fputcsv() : Ecrire dans le fichier CSV
        // $file : 1er argument, on indique le fichier dans lequel on veut ecrire
        // $dataFormulaire[''] : variable sous forme de tableau associatif et qui contient les données du formulaire, récupère la valeur associée à la clé.  exemple : ['name']
        fputcsv($fichier, [
            $dataFormulaire['name'],
            $dataFormulaire['card_number'],
            $dataFormulaire['date_expiration'],
            $dataFormulaire['CVV'],
        ]);

        // Fermez le fichier
        fclose($fichier);
    }

    // fcontion confirmation reservation et envoi pdf recapitulatif par email
    private function envoiEmailConfirmation(MailerInterface $mailer, Dompdf $dompdf, $userEmail)
    {


        // Utilisation de Twig pour générer le contenu de l'e-mail
        $contenuEmail = $this->renderView('pdf_generator/emailConfirmation.html.twig');

        // Attachement du PDF à l'e-mail
        $email = (new Email())
            ->from('no_reply_app@outlook.com') // adresse du mail DSN
            ->to($userEmail) // adresse du destinataire
            ->subject('Confirmation de réservation') // objet de l'e-mail
            ->html($contenuEmail) // on va intégrer dans le mail son contenu, on récupérere le contenu grace au twig 
            ->attach($dompdf->output(), null, 'application/pdf'); //insérer la pièce jointe

        // Envoi de l'e-mail
        $mailer->send($email);
    }

    // fonction pour intégrer la photo dans le pdf
    private function imageToBase64($path)
    {
        $path = $path;
        $type = pathinfo($path, PATHINFO_EXTENSION);
        $img = file_get_contents($path);
        $base64 = 'data:image/' . $type . ';base64,' . base64_encode($img);
        return $base64;
    }


    // Page pour récupérer l'id de la chambre et passer à la page paiement
    #[Route('/paiement/{id}', name: 'app_paiement', methods: ['GET'])]
    public function index($id,  SessionInterface $session): Response
    {
        $idChambre = $id;

        // Sauvegarde de l'ID de la chambre dans la session
        $session->set("id_chambre_reservee", $idChambre);

        // redirection vers la page paiement
        return $this->redirectToRoute("paiement");
    }



    // Page paiement lors de la validation de la reservation et redirection vers la page confirmation
    #[Route('/paiement', name: 'paiement', methods: ['GET', 'POST'])]
    public function paiement(Request $request, UserRepository $userRepository, ReservationRepository $reservationRepository, SessionInterface $session, ChambreRepository $chambreRepository, MailerInterface $mailer): Response
    {
        // recupération de l'id de la chambre réservée , va contenir l'id de la chambre (exemple : 105)
        $idChambre = $session->get("id_chambre_reservee");

        // recupération de l'utilisateur connecté
        $user = $this->getUser();

        // rechercher les infos de l'utilisateur connecté dans le repository
        $utilisateur = $userRepository->find($user);

        // rechercher les infos de la chambre  dans le repository
        $id_chambre = $chambreRepository->find($idChambre);

        // recuperation de l'id de l'utilisateur (exemple: 130)
        $userid = $utilisateur->getId();

        // initialisation d'un tableau vide
        $reservations = [];

        // Récupérer la dernière réservations associées à l'utilisateur connecté
        $maReservation = $reservationRepository->findOneBy(

            // on peut rechercher par plusieurs critéres : l'id de l'user, la dernière date de reservation, l'id de la chambre
            [
                'user' => $userid,
                'chambre' => $id_chambre,
            ],
            ['DateReservation' => 'DESC']
        );

        // stockage de la reservation dans un tableau pour affichage sur le twig
        $reservations[] = $maReservation;


        // calcul du nombre de jours entre la date entrée et la date de sortie pour multiplier par le tarif et afficher le montant total sur le twig
        // recupération des valeurs pour chaque propri
        $dateEntree = $maReservation->getDateEntree();
        $dateSortie = $maReservation->getDateSortie();

        // Calculer la différence entre les deux dates
        $difference = $dateSortie->diff($dateEntree);

        // Récupérer le nombre de jours
        $nbJours = $difference->days;

        // Créer une instance du formulaire (creation d'un objet formulaire)
        $form = $this->createForm(PaiementType::class);

        // recupération des données du formulaire
        $form->handleRequest($request);

        // Sauvegarde de la reservation dans la session pour affichage dans la page confirmation
        $session->set("reservation", $reservations);
        // dd($reservations);

        // condition: si le formulaire est soumis et valide
        if ($form->isSubmitted() && $form->isValid()) {


            // Sauvegarde de la reservation dans la session pour affichage dans la page confirmation
            $session->set("reservation", $reservations);
            // dd($reservations);

            // // alors j'enregistre les données du formulaire dans un fichier au format CSV
            // // saveToCSVfile: est la fonction qui permet l'action d'enregistrement au format CSV
            // // $form->getData: est la récupération des données
            $dataForm = $form->getData();
            $this->saveToCSVfile($dataForm);


            // creation du pdf grace au twig 
            $contenuPDF =  $this->renderView('pdf_generator/pdfMail.html.twig', [

                'reservations' => $reservations,
                'imageSrc'  => $this->imageToBase64($this->getParameter('kernel.project_dir') . '/public/img/VCH-removebg-preview_1.png'),
                'nombreJours' => $nbJours,

            ]);

            // Créer une instance de Dompdf (créer un objet PDF)
            $dompdf = new Dompdf();

            // Charger le HTML (le contenu dans le twig) dans Dompdf, on récupère la variable $html
            $dompdf->loadHtml($contenuPDF);

            // Rendu du PDF (génération du PDF)
            $dompdf->render();


            // recupération de l'utilisateur connecté
            $user = $this->getUser();

            // rechercher les infos de l'utilisateur connecté dans le repository
            $utilisateur = $userRepository->find($user);

            // recuperation de l'email de l'utilisateur 
            $userEmail = $utilisateur->getEmail();


            // Envoi de l'e-mail avec la pièce jointe PDF grace à l'appel de la fonction envoiEmailConfirmation()
            $this->envoiEmailConfirmation($mailer, $dompdf, $userEmail);

            return $this->redirectToRoute('app_confirmation');
        }

        return $this->render('paiement/index.html.twig', [

            'form' => $form->createView(), // Creation du formulaire et passer le formulaire au template Twig
            'reservations' => $reservations,
            'nombreJours' => $nbJours,
        ]);
    }
}
