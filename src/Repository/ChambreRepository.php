<?php

namespace App\Repository;

use App\Entity\Chambre;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Chambre>
 *
 * @method Chambre|null find($id, $lockMode = null, $lockVersion = null)
 * @method Chambre|null findOneBy(array $criteria, array $orderBy = null)
 * @method Chambre[]    findAll()
 * @method Chambre[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ChambreRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Chambre::class);
    }


    public function afficherChambreLibre($etat)
    {
        // Utilisation du QueryBuilder pour créer une requête SQL
        return $this->createQueryBuilder('chambre') // Alias de la table 'Categorie' nommé aussi 'categorie'
            ->select('chambre.etat') //Sélectionne uniquement la colonne 'etat', equivaut à : SELECT etat from chambre
            ->where('chambre.etat = :libre') // condition where, equivaut à : where libelle = :libelle(parametre/valeur)
            ->setParameter('libre', $etat)  // Lie la valeur true au paramètre :libre('libre')
            ->getQuery(); // Obtient l'objet de requête
        //->getScalarResult();
    }


    /**
     * @return Chambre[] Returns an array of Chambre objects
     */
    public function findEmptyRooms()
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.etat =:libre')
            ->setParameter('libre', true)
            ->getQuery()
            ->getResult();
    }

    /**
     * @return Chambre[] Returns an array of Chambre objects
     */


    public function findOneByRoom()
    {
        return $this->createQueryBuilder('c')
            ->select('r', 'c', 'u')
            ->leftJoin('c.chambre', 'r')
            ->leftJoin('r.user', 'u')
            ->andWhere('c.etat = :occupee')
            ->setParameter('occupee', false)
            ->getQuery()
            ->getResult();
    }


    public function findcountChambre(): int
    {
        return $this->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }



    public function findcountReservation(): int
    {
        return $this->createQueryBuilder('rc')
            ->select('COUNT(rc.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }


    /**
     * @return Chambre[] Returns an array of Chambre objects
     */


    public function calculateChiffreDaffairesByMonth(\DateTime $startDate, \DateTime $endDate): array
    {
        $query = $this->createQueryBuilder('c')
            ->select('YEAR(r.DateEntree) AS annee, MONTH(r.DateEntree) AS mois, SUM(DATE_DIFF(r.DateSortie, r.DateEntree) * c.tarif) AS chiffre')
            ->leftJoin('c.chambre', 'r')
            ->where('r.DateEntree <= :endDate')
            ->andWhere('r.DateSortie >= :startDate')
            ->groupBy('annee, mois')
            ->orderBy('annee, mois')
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->getQuery();

        return $query->getResult();
    }

}
