<?php

namespace App\Repository;

use App\Entity\Facture;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Facture>
 */
class FactureRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Facture::class);
    }

    /**
     * Sauvegarde une facture en base
     */
    public function save(Facture $facture, bool $flush = false): void
    {
        $this->_em->persist($facture);

        if ($flush) {
            $this->_em->flush();
        }
    }

    /**
     * Supprime une facture en base
     */
    public function remove(Facture $facture, bool $flush = false): void
    {
        $this->_em->remove($facture);

        if ($flush) {
            $this->_em->flush();
        }
    }

    /**
     * Exemple : Trouver une facture par son numéro
     */
    public function findOneByNumero(string $numero): ?Facture
    {
        return $this->createQueryBuilder('f')
            ->andWhere('f.numeroFacture = :num')
            ->setParameter('num', $numero)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Exemple : Trouver toutes les factures d’un fournisseur donné
     */
    public function findByFournisseur(string $sirenFournisseur): array
    {
        return $this->createQueryBuilder('f')
            ->andWhere('f.sirenFournisseur = :siren')
            ->setParameter('siren', $sirenFournisseur)
            ->orderBy('f.dateFacture', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Exemple : Factures en retard (échéance dépassée et non payées)
     */
    public function findOverdue(): array
    {
        return $this->createQueryBuilder('f')
            ->andWhere('f.dateEcheance < :now')
            ->andWhere('f.netAPayer > 0')
            ->setParameter('now', new \DateTimeImmutable())
            ->orderBy('f.dateEcheance', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
