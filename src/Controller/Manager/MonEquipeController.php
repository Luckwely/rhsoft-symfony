<?php
namespace App\Controller\Manager;

use App\Entity\User;
use App\Entity\Planning;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;


#[Route('/manager')]
#[IsGranted('ROLE_MANAGER')]
class MonEquipeController extends AbstractController
{
    #[Route('/monEquipe', name: 'app_manager_monEquipe')]
    public function list(
        Request $request,
        EntityManagerInterface $em,
        PaginatorInterface $paginator
    ): Response
    {

        $entreprise = $this->getUser()->getEntreprise();

        $mondayThisWeek = new \DateTime('monday this week');
        $plannings = $em->getRepository(Planning::class)->findBy([
            'entreprise' => $entreprise,
            'weekStart' => $mondayThisWeek
        ]);
        $planningMap = [];
        foreach($plannings as $p){
            $planningMap[$p->getUser()->getId()] = $p;
        }

        $queryBuilder = $em->getRepository(User::class)->createQueryBuilder('u')
            ->where('u.entreprise = :entreprise')
            ->andWhere('u.is_active = :active')
            ->setParameter('entreprise', $entreprise)
            ->setParameter('active', true);

        // RECHERCHE
        if ($search = $request->query->get('q')) {
            $queryBuilder
                ->andWhere('u.nom LIKE :search OR u.prenom LIKE :search OR u.email LIKE :search')
                ->setParameter('search', '%'.$search.'%');
        }

        $employees = $paginator->paginate(
            $queryBuilder,
            $request->query->getInt('page', 1),
            10
        );

        return $this->render('manager/monEquipe/list.html.twig', [
            'employees' => $employees,
            'planningMap' => $planningMap
        ]);
    }

}
