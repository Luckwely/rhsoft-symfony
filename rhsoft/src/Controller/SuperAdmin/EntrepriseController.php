<?php
namespace App\Controller\SuperAdmin;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use App\Repository\EntrepriseRepository;
use App\Repository\UserRepository;
use Knp\Component\Pager\PaginatorInterface;
use Doctrine\ORM\EntityManagerInterface;
use App\Entity\Entreprise;

#[Route('/super/admin')]
#[IsGranted('ROLE_SUPER_ADMIN')]
final class EntrepriseController extends AbstractController
{
    #[Route('/entreprise', name: 'app_super_admin_entreprise')]
    public function index(Request $request, EntrepriseRepository $repo, PaginatorInterface $paginator): Response
    {
        $search = $request->query->get('q');
        $status = $request->query->get('status');
        $query = $repo->findAllWithStats($search, $status);

        $pagination = $paginator->paginate($query, $request->query->getInt('page', 1), 8);

        return $this->render('super_admin/entreprise/list.html.twig', [
            'pagination' => $pagination,
            'stats' => [
                'total' => $repo->countAll(),
                'actifs' => $repo->countByStatus('active'),
                'essai' => $repo->countByStatus('trial'),
                'inactifs' => $repo->countByStatus('inactif')
            ]
        ]);
    }

    #[Route('/entreprise/{id}', name: 'app_super_admin_entreprise_details')]
    public function show(int $id, Request $request, EntrepriseRepository $repoEntreprise, UserRepository $repoUser, PaginatorInterface $paginator): Response
    {
        $entreprise = $repoEntreprise->find($id);
        if (!$entreprise){ throw $this->createNotFoundException('Entreprise introuvable'); }

        $search = $request->query->get('q');
        $status = $request->query->get('status');

        $queryUser = $repoUser->findByEntrepriseWithStats($id, $search, $status);
        $pagination = $paginator->paginate($queryUser, $request->query->getInt('page', 1), 8);

        $activeTab = $request->query->get('tab', 'info');
        $nbUsers = $repoEntreprise->countUsersByEntreprise($id);
        $admin = $repoEntreprise->findOneAdminByEntreprise($id);

        return $this->render('super_admin/entreprise/detail.html.twig', [
            'entreprise' => $entreprise,
            'admin' => $admin,
            'nbUsers' => $nbUsers,
            'pagination' => $pagination,
            'activeTab' => $activeTab,
        ]);
    }

    #[Route('/entreprise/{id}/toggle-status', name: 'app_super_admin_entreprise_toggle')]
    public function toggleStatus(Entreprise $entreprise, EntrepriseRepository $repo, EntityManagerInterface $em): Response
    {
        $newStatus = $entreprise->getStatus() === 'active' ? 'suspendu' : 'active';
        $entreprise->setStatus($newStatus);

        // Optionnel: désactiver aussi tous les users
        foreach($entreprise->getUsers() as $user){
            $user->setIsActive($newStatus === 'active');
        }
        //foreach($entreprise->getUsers() as $user){
        //    $user->setIsActive(false); // when suspend
        //   $user->setIsActive(true);  // when reactivate
        //}

        $em->flush();
        $this->addFlash('success', 'Statut de l\'entreprise mis à jour');
        return $this->redirectToRoute('app_super_admin_entreprise');
    }

}
