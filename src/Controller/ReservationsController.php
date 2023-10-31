<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use App\Repository\ReservationRepository;
use Nelmio\CorsBundle\Annotation\Cors;
class ReservationsController extends AbstractController
{
    #[Route('/reservations', name: 'app_reservations') ]
    public function index(ReservationRepository $reservationRepository): JsonResponse
    {
        $reservations = $reservationRepository->findAllReservations();
        // dd($reservations);
        $data = [];
        foreach ($reservations as $reservation) {
            $data[] = [
                'id' => $reservation->getId(),
                'dateReservation' => $reservation->getDateReservation()->format('Y-m-d'),
                'dateEntree' => $reservation->getDateEntree()->format('Y-m-d'),
                'dateSortie' => $reservation->getDateSortie()->format('Y-m-d'),
                'user' => [
                    'id' => $reservation->getUser()->getId(),
                    
                ],
                'chambre' => [
                    'id' => $reservation->getChambre()->getId(),
                    
                ],
            ];
        }

        return $this->json([
            'messages'  =>$data]);
    }
}