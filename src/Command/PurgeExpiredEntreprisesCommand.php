<?php

namespace App\Command;

use App\Entity\Entreprise;
use App\Entity\SystemLog;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 *
 *
 *
 *
 *
 *
 *
 *
 *
 *
 *
 */
#[AsCommand(
    name: 'app:purge-expired-entreprises',
    description: 'Supprime définitivement les entreprises dont l\'abonnement est expiré/annulé depuis plus de 2 ans sans avoir été renouvelé.'
)]
class PurgeExpiredEntreprisesCommand extends Command
{

    private const NON_RENEWED_STATUSES = ['expired', 'canceled', 'pending'];

    private const RETENTION_YEARS = 2;

    public function __construct(private EntityManagerInterface $em)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'List the entreprises that would be deleted, without deleting anything')
            ->addOption('force', null, InputOption::VALUE_NONE, 'Actually delete (required in addition to omitting --dry-run, as a safety net)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dryRun = (bool) $input->getOption('dry-run');
        $force = (bool) $input->getOption('force');

        $threshold = (new \DateTime())->modify('-' . self::RETENTION_YEARS . ' years');

        $candidates = $this->em->getRepository(Entreprise::class)->createQueryBuilder('e')
            ->andWhere('e.status IN (:statuses)')
            ->andWhere('e.date_fin_abonnement IS NOT NULL')
            ->andWhere('e.date_fin_abonnement < :threshold')
            ->setParameter('statuses', self::NON_RENEWED_STATUSES)
            ->setParameter('threshold', $threshold)
            ->getQuery()
            ->getResult();

        if (empty($candidates)) {
            $io->success('Aucune entreprise à purger (aucune entreprise expirée depuis plus de ' . self::RETENTION_YEARS . ' ans sans renouvellement).');
            return Command::SUCCESS;
        }

        $io->section(sprintf('%d entreprise(s) inactive(s) depuis plus de %d ans (statut non renouvelé) :', count($candidates), self::RETENTION_YEARS));
        $rows = [];
        foreach ($candidates as $entreprise) {
            /** @var Entreprise $entreprise */
            $rows[] = [
                $entreprise->getId(),
                $entreprise->getNom(),
                $entreprise->getStatus(),
                $entreprise->getDateFinAbonnement()?->format('Y-m-d'),
            ];
        }
        $io->table(['ID', 'Nom', 'Statut', 'Fin abonnement'], $rows);

        if ($dryRun) {
            $io->note('Mode --dry-run : aucune suppression effectuée.');
            return Command::SUCCESS;
        }

        if (!$force) {
            $io->warning('Ajoutez --force en plus (sans --dry-run) pour confirmer la suppression définitive de ces données.');
            return Command::INVALID;
        }

        foreach ($candidates as $entreprise) {
            $this->purgeEntreprise($entreprise, $io);
        }

        $io->success(sprintf('%d entreprise(s) supprimée(s) définitivement.', count($candidates)));
        return Command::SUCCESS;
    }

    private function purgeEntreprise(Entreprise $entreprise, SymfonyStyle $io): void
    {
        $conn = $this->em->getConnection();
        $conn->beginTransaction();

        try {
            $entrepriseId = $entreprise->getId();
            $nom = $entreprise->getNom();

            $userIds = array_map(
                static fn (User $u) => $u->getId(),
                $this->em->getRepository(User::class)->findBy(['entreprise' => $entreprise])
            );


            $dql = $this->em->createQuery('DELETE FROM App\Entity\Candidature c WHERE c.offre IN (SELECT o.id FROM App\Entity\Offre o WHERE o.entreprise = :e)');
            $dql->setParameter('e', $entreprise)->execute();

            $this->em->createQuery('DELETE FROM App\Entity\Offre o WHERE o.entreprise = :e')->setParameter('e', $entreprise)->execute();

            if (!empty($userIds)) {
                $this->em->createQuery('DELETE FROM App\Entity\Paie p WHERE p.employee IN (:ids) OR p.validePar IN (:ids)')->setParameter('ids', $userIds)->execute();
            }

            $this->em->createQuery('DELETE FROM App\Entity\AvanceSalaire a WHERE a.entreprise = :e')->setParameter('e', $entreprise)->execute();
            $this->em->createQuery('DELETE FROM App\Entity\Conge c WHERE c.entreprise = :e')->setParameter('e', $entreprise)->execute();
            $this->em->createQuery('DELETE FROM App\Entity\Pointage p WHERE p.entreprise = :e')->setParameter('e', $entreprise)->execute();
            $this->em->createQuery('DELETE FROM App\Entity\Planning p WHERE p.entreprise = :e')->setParameter('e', $entreprise)->execute();
            $this->em->createQuery('DELETE FROM App\Entity\Demission d WHERE d.entreprise = :e')->setParameter('e', $entreprise)->execute();

            $this->em->createQuery('DELETE FROM App\Entity\User u WHERE u.entreprise = :e')->setParameter('e', $entreprise)->execute();


            $log = new SystemLog();
            $log->setLoggedAt(new \DateTimeImmutable());
            $log->setLevel('warning');
            $log->setCompanyName($nom);
            $log->setMessage(sprintf(
                'Purge automatique RGPD/rétention : entreprise #%d "%s" supprimée (statut non renouvelé depuis plus de %d ans).',
                $entrepriseId,
                $nom,
                self::RETENTION_YEARS
            ));
            $this->em->persist($log);
            $this->em->flush();

            $this->em->remove($entreprise);
            $this->em->flush();

            $conn->commit();
            $io->text(sprintf('  ✔ Entreprise #%d "%s" supprimée.', $entrepriseId, $nom));
        } catch (\Throwable $e) {
            $conn->rollBack();
            $io->error(sprintf('Échec de la suppression de "%s" : %s', $entreprise->getNom(), $e->getMessage()));
        }
    }
}
